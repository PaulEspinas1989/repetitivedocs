<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;

class TemplateEditorController extends Controller
{
    public function show(Template $template): View
    {
        $this->authorizeWorkspace($template);

        $template->load(['variables' => fn($q) => $q
            ->select(['id','template_id','workspace_id','name','label','type',
                      'description','example_value','approval_status',
                      'occurrences','is_required','sort_order','ai_suggested',
                      'text_positions']) // needed for page-number display in variable-card
            ->orderBy('sort_order')
        ]);
        $this->syncReadiness($template);

        return view('template-editor', [
            'template'  => $template,
            'readiness' => $template->readiness_score ?? 0,
            'pending'   => $template->variables->where('approval_status', 'pending'),
            'approved'  => $template->variables->where('approval_status', 'approved'),
            'rejected'  => $template->variables->where('approval_status', 'rejected'),
        ]);
    }

    public function approveVariable(Template $template, TemplateVariable $variable): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $variable->update(['approval_status' => 'approved']);
        $this->syncReadiness($template);

        return back()->with('toast', '"' . $variable->label . '" approved.');
    }

    public function rejectVariable(Template $template, TemplateVariable $variable): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $variable->update(['approval_status' => 'rejected']);
        $this->syncReadiness($template);

        return back()->with('toast', '"' . $variable->label . '" rejected.');
    }

    public function updateVariable(Request $request, Template $template, TemplateVariable $variable): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'label'       => ['required', 'string', 'max:100'],
            'type'        => ['required', 'string', 'in:text,date,number,currency,email,phone,address,select'],
            'is_required' => ['boolean'],
        ]);

        if ($validator->fails()) {
            // Store variable ID so the view opens ONLY this card's edit form
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('error_variable_id', $variable->id);
        }

        $variable->update([
            'label'       => $request->label,
            'type'        => $request->type,
            'is_required' => $request->boolean('is_required'),
        ]);

        return back()->with('toast', 'Field "' . $request->label . '" updated.');
    }

    public function undoVariable(Template $template, TemplateVariable $variable): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $variable->update(['approval_status' => 'pending']);
        $this->syncReadiness($template);

        return back()->with('toast', '"' . $variable->label . '" moved back to pending.');
    }

    public function approveAll(Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $template->variables()->where('approval_status', 'pending')->update(['approval_status' => 'approved']);
        $this->syncReadiness($template);

        return back()->with('toast', 'All pending variables approved.');
    }

    /**
     * Quick inline value-mode update from the editor.
     * Full management is done through FixedFieldsController::updateVariableMode.
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
        $updates = ['value_mode' => $mode, 'user_confirmed_mode' => true];

        if ($mode === TemplateVariable::MODE_FIXED && $request->filled('fixed_value')) {
            $updates['fixed_value']                = $request->fixed_value;
            $updates['fixed_value_set_by_user_id'] = auth()->id();
            $updates['fixed_value_set_at']         = now();
        } elseif ($mode === TemplateVariable::MODE_DEFAULT && $request->filled('fixed_value')) {
            $updates['default_value'] = $request->fixed_value;
        } elseif ($mode === TemplateVariable::MODE_ASK) {
            $updates['fixed_value']   = null;
            $updates['default_value'] = null;
        }

        $variable->update($updates);

        return back()->with('toast', '"' . $variable->label . '" updated to "' . TemplateVariable::MODE_LABELS[$mode] . '".');
    }

    // ── Private helpers ──────────────────────────────────────────────

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

    private function syncReadiness(Template $template): void
    {
        // Two fresh queries — avoids shallow-clone issues with shared Eloquent query builders
        $total    = $template->variables()->count();
        $approved = $template->variables()->where('approval_status', 'approved')->count();

        $score = $total > 0 ? (int) round(($approved / $total) * 100) : 0;

        $template->update(['readiness_score' => $score]);
        $template->readiness_score = $score;
    }
}
