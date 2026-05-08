<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Services\DateFormatterService;
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

        // Keep-as-constant selections: [ var_name => '1' ]
        $keepAsConstant = $request->input('keep_as_constant', []);

        // Strip peso sign and commas from currency fields
        // Format date fields from ISO (YYYY-MM-DD) to the template's detected format
        foreach ($template->approvedVariables as $var) {
            if ($var->type === 'currency') {
                if (isset($userValues[$var->name])) {
                    $userValues[$var->name] = preg_replace('/[₱,\s]/', '', $userValues[$var->name]);
                }
                if (isset($overrides[$var->name])) {
                    $overrides[$var->name] = preg_replace('/[₱,\s]/', '', $overrides[$var->name]);
                }
            } elseif ($var->type === 'date') {
                // Format the ISO date into the document's expected format before generation.
                // e.g. '2026-05-30' + date_format='F j, Y' → 'May 30, 2026'
                if (!empty($userValues[$var->name])) {
                    $userValues[$var->name] = $this->dateFormatter->format(
                        $userValues[$var->name],
                        $var->date_format
                    );
                }
                if (!empty($overrides[$var->name])) {
                    $overrides[$var->name] = $this->dateFormatter->format(
                        $overrides[$var->name],
                        $var->date_format
                    );
                }
            }
        }

        try {
            $generated = $this->generator->generate($template, $userValues, $overrides);

            // Save keep-as-constant values as fixed fields after successful generation.
            // We save AFTER generation so a failed generation never silently locks a value.
            foreach ($template->approvedVariables as $var) {
                if (empty($keepAsConstant[$var->name])) {
                    continue;
                }
                $valueToSave = $userValues[$var->name] ?? $overrides[$var->name] ?? null;
                if ($valueToSave === null || $valueToSave === '') {
                    continue;
                }
                $var->update([
                    'value_mode'                       => TemplateVariable::MODE_FIXED,
                    'fixed_value'                      => $valueToSave,
                    'fixed_value_set_by_user_id'       => auth()->id(),
                    'fixed_value_set_at'               => now(),
                    'fixed_value_set_by_generation_id' => $generated->id,
                    'user_confirmed_mode'              => true,
                    'show_when_fixed'                  => false,
                ]);
            }

            return redirect()->route('generation-result', $generated->id);
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
