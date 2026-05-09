<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDocumentJob;
use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Services\DateFormatterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class FillableFormController extends Controller
{
    public function __construct(
        private DateFormatterService $dateFormatter,
    ) {}

    public function show(Template $template): View|RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $template->load(['approvedVariables']);

        if ($template->approvedVariables->isEmpty()) {
            return redirect()->route('templates.editor', $template->id)
                ->with('toast', 'Approve at least one variable before generating.');
        }

        $formVars  = $template->approvedVariables->filter(fn($v) => !$v->isHiddenFromForm());
        $fixedVars = $template->approvedVariables->filter(fn($v) => $v->isFixed());

        return view('fillable-form', compact('template', 'formVars', 'fixedVars'));
    }

    public function generate(Request $request, Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $template->load(['approvedVariables']);

        $rules = [];
        foreach ($template->approvedVariables as $var) {
            if ($var->isHiddenFromForm()) {
                continue;
            }

            $rule = $var->is_required ? ['required', 'string', 'max:500'] : ['nullable', 'string', 'max:500'];
            if ($var->type === 'email') {
                $rule[] = 'email';
            } elseif ($var->type === 'date') {
                $rule = $var->is_required ? ['required', 'date'] : ['nullable', 'date'];
            } elseif ($var->type === 'number') {
                $rule = $var->is_required ? ['required', 'numeric'] : ['nullable', 'numeric'];
            }
            $rules['fields.' . $var->name] = $rule;
        }

        $validated      = $request->validate($rules);
        $userValues     = $validated['fields'] ?? [];
        $overrides      = $request->input('overrides', []);
        $keepAsConstant = $request->input('keep_as_constant', []);

        // Pre-format values before handing off to the job
        foreach ($template->approvedVariables as $var) {
            if ($var->type === 'currency') {
                if (isset($userValues[$var->name])) {
                    $userValues[$var->name] = preg_replace('/[₱,\s]/', '', $userValues[$var->name]);
                }
                if (isset($overrides[$var->name])) {
                    $overrides[$var->name] = preg_replace('/[₱,\s]/', '', $overrides[$var->name]);
                }
            } elseif ($var->type === 'date') {
                if (!empty($userValues[$var->name])) {
                    $userValues[$var->name] = $this->dateFormatter->format($userValues[$var->name], $var->date_format);
                }
                if (!empty($overrides[$var->name])) {
                    $overrides[$var->name] = $this->dateFormatter->format($overrides[$var->name], $var->date_format);
                }
            }
        }

        // Create a pending GeneratedDocument record immediately so the user can poll it.
        // The job fills in file_path, file_name, and flips status to 'ready' on success.
        $pending = GeneratedDocument::create([
            'workspace_id'    => $template->workspace_id,
            'user_id'         => auth()->id(),
            'template_id'     => $template->id,
            'variable_values' => $userValues,
            'file_path'       => '',
            'file_name'       => '',
            'disk'            => 'documents',
            'status'          => 'processing',
        ]);

        GenerateDocumentJob::dispatch($pending, $template, $overrides, $keepAsConstant);

        return redirect()->route('generation-loading', $pending->id);
    }

    // ── Status endpoint polled by generation-loading page ───────────

    public function status(GeneratedDocument $generated): JsonResponse
    {
        $this->authorizeGeneratedDocument($generated);

        return match ($generated->status) {
            'ready' => response()->json([
                'success'  => true,
                'redirect' => route('generation-result', $generated->id),
            ]),
            'failed' => response()->json([
                'error'   => true,
                'message' => $generated->error_message ?? 'Document generation failed. Please try again.',
            ], 500),
            default => response()->json(['status' => 'processing'], 202),
        };
    }

    public function download(GeneratedDocument $generated): StreamedResponse
    {
        $this->authorizeGeneratedDocument($generated);

        return Storage::disk($generated->disk)->download(
            $generated->file_path,
            $generated->file_name
        );
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function authorizeWorkspace(Template $template): void
    {
        $ids = auth()->user()->workspaces()->pluck('workspaces.id')->toArray();
        if (!in_array($template->workspace_id, $ids)) {
            abort(403);
        }
    }

    private function authorizeGeneratedDocument(GeneratedDocument $document): void
    {
        $ids = auth()->user()->workspaces()->pluck('workspaces.id')->toArray();
        if (!in_array($document->workspace_id, $ids)) {
            abort(403);
        }
    }
}
