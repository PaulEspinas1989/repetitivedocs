<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class FillableFormController extends Controller
{
    public function __construct(private DocumentGenerationService $generator) {}

    public function show(Template $template): View
    {
        $this->authorizeWorkspace($template);

        $template->load(['approvedVariables' => fn($q) => $q->orderBy('sort_order')]);

        if ($template->approvedVariables->isEmpty()) {
            return redirect()->route('templates.editor', $template->id)
                ->with('toast', 'Approve at least one variable before generating.');
        }

        return view('fillable-form', compact('template'));
    }

    public function generate(Request $request, Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $template->load(['approvedVariables' => fn($q) => $q->orderBy('sort_order')]);

        // Build validation rules dynamically
        $rules = [];
        foreach ($template->approvedVariables as $var) {
            $rule = $var->is_required ? ['required', 'string', 'max:500'] : ['nullable', 'string', 'max:500'];
            if ($var->type === 'email') {
                $rule[] = 'email';
            } elseif ($var->type === 'date') {
                $rule = $var->is_required ? ['required', 'date'] : ['nullable', 'date'];
            } elseif ($var->type === 'number') {
                // number fields use HTML number input — validate as numeric
                $rule = $var->is_required ? ['required', 'numeric'] : ['nullable', 'numeric'];
            }
            // currency stays as string — user may enter "1,000,000" with commas
            $rules['fields.' . $var->name] = $rule;
        }

        $validated = $request->validate($rules);
        $values    = $validated['fields'] ?? [];

        // Sanitize currency fields: strip peso sign and commas so they store as plain numbers
        foreach ($template->approvedVariables as $var) {
            if ($var->type === 'currency' && isset($values[$var->name])) {
                $values[$var->name] = preg_replace('/[₱,\s]/', '', $values[$var->name]);
            }
        }

        try {
            $generated = $this->generator->generate($template, $values);
            return redirect()->route('generation-result', $generated->id);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Document generation failed: ' . $e->getMessage());
        }
    }

    public function download(GeneratedDocument $generated): StreamedResponse
    {
        $ids = auth()->user()->workspaces()->pluck('workspaces.id')->toArray();
        if (!in_array($generated->workspace_id, $ids)) {
            abort(403);
        }

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

    private function authorizeWorkspaceForDoc(GeneratedDocument $document): void
    {
        $ids = auth()->user()->workspaces()->pluck('workspaces.id')->toArray();
        if (!in_array($document->workspace_id, $ids)) {
            abort(403);
        }
    }
}
