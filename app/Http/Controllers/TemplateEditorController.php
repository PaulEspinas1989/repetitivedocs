<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateEditorController extends Controller
{
    public function show(Template $template): View
    {
        $this->authorizeWorkspace($template);

        $template->load(['variables' => fn($q) => $q->orderBy('sort_order')]);
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

        return back()->with('toast', 'Variable approved.');
    }

    public function rejectVariable(Template $template, TemplateVariable $variable): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $variable->update(['approval_status' => 'rejected']);
        $this->syncReadiness($template);

        return back()->with('toast', 'Variable rejected.');
    }

    public function updateVariable(Request $request, Template $template, TemplateVariable $variable): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $request->validate([
            'label'       => ['required', 'string', 'max:100'],
            'type'        => ['required', 'string', 'in:text,date,number,currency,email,phone,address,select'],
            'is_required' => ['boolean'],
        ]);

        $variable->update([
            'label'       => $request->label,
            'type'        => $request->type,
            'is_required' => $request->boolean('is_required', true),
        ]);

        return back()->with('toast', 'Variable updated.');
    }

    public function undoVariable(Template $template, TemplateVariable $variable): RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $variable->update(['approval_status' => 'pending']);
        $this->syncReadiness($template);

        return back()->with('toast', 'Variable moved back to pending.');
    }

    public function approveAll(Template $template): RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $template->variables()->where('approval_status', 'pending')->update(['approval_status' => 'approved']);
        $this->syncReadiness($template);

        return back()->with('toast', 'All pending variables approved.');
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
        $template->loadCount([
            'variables',
            'variables as approved_count' => fn($q) => $q->where('approval_status', 'approved'),
        ]);

        $total    = $template->variables_count;
        $approved = $template->approved_count;

        $score = $total > 0 ? (int) round(($approved / $total) * 100) : 0;

        $template->update(['readiness_score' => $score]);
        $template->readiness_score = $score;
    }
}
