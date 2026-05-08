<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Services\GenerationValueResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles the Fixed Fields Review flow — after first generation,
 * user reviews which answers to remember for next time.
 *
 * Also handles the Upload Decision (Generate Once vs Save as Template).
 */
class FixedFieldsController extends Controller
{
    public function __construct(
        private GenerationValueResolverService $resolver
    ) {}

    // ── Upload Decision ───────────────────────────────────────────────

    /**
     * Show the "Generate Once or Save as Template?" decision screen.
     * Triggered after AI scan completes and user has reviewed the automation map.
     */
    public function uploadDecision(Template $template): View|RedirectResponse
    {
        $this->authorizeWorkspace($template);

        // If already decided, skip this screen
        if ($template->save_mode !== Template::SAVE_DRAFT) {
            return redirect()->route('templates.editor', $template->id);
        }

        return view('upload-decision', compact('template'));
    }

    /**
     * User chose "Generate Once" — mark template as one-time, go to fillable form.
     */
    public function chooseGenerateOnce(Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $template->update(['save_mode' => Template::SAVE_ONE_TIME]);

        return redirect()->route('fillable-form', $template->id)
            ->with('toast', 'Fill in the fields and your document will be ready.');
    }

    /**
     * User chose "Save as Template" — mark as saved, go to template editor.
     */
    public function chooseSaveAsTemplate(Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $template->markSaved(auth()->id());

        return redirect()->route('templates.editor', $template->id)
            ->with('toast', 'Template saved! Approve your fields to start generating.');
    }

    /**
     * Convert a one-time template to a saved template (from the generation result screen).
     */
    public function saveAsTemplate(Request $request, Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        if ($request->filled('name')) {
            $template->name = $request->name;
        }

        $template->markSaved(auth()->id());

        return redirect()->route('fixed-fields.review', $template->id)
            ->with('toast', 'Template saved! Now choose what to remember for next time.');
    }

    // ── Fixed Fields Review ───────────────────────────────────────────

    /**
     * Show the Fixed Fields Review screen.
     * User reviews each approved variable and sets its value mode.
     *
     * Shown after:
     * - First generation from a saved template
     * - User clicks "Review saved answers" on the generation result
     */
    public function review(Template $template, ?GeneratedDocument $generated = null): View
    {
        $this->authorizeWorkspace($template);

        $template->load(['approvedVariables']);

        // Get the last-used values — from a specific generation or the most recent
        $lastValues = [];
        if ($generated) {
            $lastValues = $generated->variable_values ?? [];
        } else {
            $latest = $template->generatedDocuments()->first();
            $lastValues = $latest?->variable_values ?? [];
        }

        // Get AI mode suggestions for each variable
        $suggestions = $this->resolver->suggestModes($template, $lastValues);

        return view('fixed-fields-review', compact('template', 'generated', 'lastValues', 'suggestions'));
    }

    /**
     * Save the user's value mode choices for all variables.
     */
    public function saveReview(Request $request, Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $request->validate([
            'modes'        => ['required', 'array'],
            'modes.*'      => ['required', 'string', 'in:ask_each_time,default_editable,fixed_hidden'],
            'fixed_values' => ['nullable', 'array'],
            'fixed_values.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $modes       = $request->input('modes', []);
        $fixedValues = $request->input('fixed_values', []);
        $lastGenId   = $request->input('generation_id');

        foreach ($template->approvedVariables as $var) {
            $mode = $modes[$var->name] ?? TemplateVariable::MODE_ASK;
            $updates = [
                'value_mode'          => $mode,
                'user_confirmed_mode' => true,
            ];

            if ($mode === TemplateVariable::MODE_FIXED) {
                $fixedVal = $fixedValues[$var->name] ?? null;
                if ($fixedVal !== null && $fixedVal !== '') {
                    $updates['fixed_value']                      = $fixedVal;
                    $updates['fixed_value_set_by_user_id']       = auth()->id();
                    $updates['fixed_value_set_at']               = now();
                    $updates['fixed_value_set_by_generation_id'] = $lastGenId ?: null;
                }
            } elseif ($mode === TemplateVariable::MODE_DEFAULT) {
                // Use the submitted value as the new default
                $defVal = $fixedValues[$var->name] ?? null;
                if ($defVal !== null && $defVal !== '') {
                    $updates['default_value'] = $defVal;
                }
            } else {
                // ask_each_time — clear any fixed/default overrides
                $updates['fixed_value']   = null;
                $updates['default_value'] = null;
            }

            $var->update($updates);
        }

        $template->markFixedFieldsReviewed();

        return redirect()->route('fillable-form', $template->id)
            ->with('toast', 'Saved! Loopi will remember your fixed fields from now on.');
    }

    // ── Variable Value Mode Management ────────────────────────────────

    /**
     * Update a single variable's value mode (from Template Editor).
     */
    public function updateVariableMode(Request $request, Template $template, TemplateVariable $variable): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $request->validate([
            'value_mode'  => ['required', 'string', 'in:ask_each_time,default_editable,fixed_hidden'],
            'fixed_value' => ['nullable', 'string', 'max:1000'],
        ]);

        $mode    = $request->value_mode;
        $updates = [
            'value_mode'          => $mode,
            'user_confirmed_mode' => true,
        ];

        if ($mode === TemplateVariable::MODE_FIXED) {
            if ($request->filled('fixed_value')) {
                $updates['fixed_value']                      = $request->fixed_value;
                $updates['fixed_value_set_by_user_id']       = auth()->id();
                $updates['fixed_value_set_at']               = now();
            }
        } elseif ($mode === TemplateVariable::MODE_DEFAULT) {
            if ($request->filled('fixed_value')) {
                $updates['default_value'] = $request->fixed_value;
            }
        } else {
            // Reset to ask_each_time — clear stored values
            $updates['fixed_value']   = null;
            $updates['default_value'] = null;
        }

        $variable->update($updates);

        $modeLabel = TemplateVariable::MODE_LABELS[$mode] ?? $mode;

        return back()->with('toast', "\"{$variable->label}\" set to \"{$modeLabel}\".");
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function authorizeWorkspace(Template $template): void
    {
        if ((int) $template->workspace_id !== (int) auth()->user()->active_workspace_id) {
            abort(403);
        }
    }

    private function authorizeVariable(Template $template, TemplateVariable $variable): void
    {
        if ((int) $variable->template_id !== (int) $template->id) {
            abort(403);
        }
    }
}
