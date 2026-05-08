<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Services\DocumentGenerationService;
use App\Services\GenerationValueResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class FillableFormController extends Controller
{
    public function __construct(
        private DocumentGenerationService $generator,
        private GenerationValueResolverService $resolver,
    ) {}

    public function show(Template $template): View|RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $template->load(['approvedVariables']);

        if ($template->approvedVariables->isEmpty()) {
            return redirect()->route('templates.editor', $template->id)
                ->with('toast', 'Approve at least one variable before generating.');
        }

        // Variables the user needs to fill (not fixed_hidden)
        $formVars   = $template->approvedVariables->filter(fn($v) => !$v->isHiddenFromForm());
        // Variables that are fixed and auto-filled (shown in summary only)
        $fixedVars  = $template->approvedVariables->filter(fn($v) => $v->isFixed());

        return view('fillable-form', compact('template', 'formVars', 'fixedVars'));
    }

    public function generate(Request $request, Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $template->load(['approvedVariables']);

        // Only build validation rules for fields the user must fill
        // Fixed fields are not submitted and not validated here
        $rules = [];
        foreach ($template->approvedVariables as $var) {
            if ($var->isHiddenFromForm()) {
                continue; // fixed_hidden — auto-filled, no user input required
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

        $validated  = $request->validate($rules);
        $userValues = $validated['fields'] ?? [];

        // One-time overrides: user chose "Use a different value this time" for a fixed field
        $overrides = $request->input('overrides', []);

        // Strip peso sign and commas from currency fields
        foreach ($template->approvedVariables as $var) {
            if ($var->type === 'currency') {
                if (isset($userValues[$var->name])) {
                    $userValues[$var->name] = preg_replace('/[₱,\s]/', '', $userValues[$var->name]);
                }
                if (isset($overrides[$var->name])) {
                    $overrides[$var->name] = preg_replace('/[₱,\s]/', '', $overrides[$var->name]);
                }
            }
        }

        try {
            $generated = $this->generator->generate($template, $userValues, $overrides);

            // After first generation from a saved template, prompt Fixed Fields Review
            $redirectRoute = $template->isSavedTemplate() && !$template->fixed_fields_reviewed
                ? 'generation-result'
                : 'generation-result';

            return redirect()->route($redirectRoute, $generated->id);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Document generation failed: ' . $e->getMessage());
        }
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
