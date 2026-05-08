<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateVariable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateEditorController extends Controller
{
    public function show(Template $template): View
    {
        $this->authorizeWorkspace($template);

        $template->load(['variables' => fn($q) => $q
            ->select(['id','template_id','workspace_id','name','label','type',
                      'description','example_value','approval_status',
                      'occurrences','is_required','sort_order','ai_suggested',
                      'text_positions','needs_review','needs_review_reason',
                      'value_mode','fixed_value','default_value'])
            ->orderBy('sort_order')
        ]);

        // syncReadiness() now returns counts in the same GROUP BY query — no extra trips.
        // We don't need the counts here (view derives them from collections), but calling
        // this ensures readiness_score is always fresh on page load.
        $this->syncReadiness($template);

        $vars = $template->variables;

        // Split pending by AI confidence. Compute in controller, not view, for testability.
        $allPending  = $vars->where('approval_status', 'pending');
        $needsReview = $allPending->filter(fn($v) => (bool) $v->needs_review);
        $pending     = $allPending->filter(fn($v) => !(bool) $v->needs_review);
        $approved    = $vars->where('approval_status', 'approved');
        $rejected    = $vars->where('approval_status', 'rejected');

        // Pre-compute repeating/standalone splits for each tab so the view doesn't re-filter.
        $groupedVars = [
            'pending'  => [
                'repeating'  => $pending->filter(fn($v) => ($v->occurrences ?: 1) > 1),
                'standalone' => $pending->filter(fn($v) => ($v->occurrences ?: 1) <= 1),
            ],
            'approved' => [
                'repeating'  => $approved->filter(fn($v) => ($v->occurrences ?: 1) > 1),
                'standalone' => $approved->filter(fn($v) => ($v->occurrences ?: 1) <= 1),
            ],
            'rejected' => [
                'repeating'  => $rejected->filter(fn($v) => ($v->occurrences ?: 1) > 1),
                'standalone' => $rejected->filter(fn($v) => ($v->occurrences ?: 1) <= 1),
            ],
        ];
        $allVars = $pending->concat($approved)->concat($rejected)->concat($needsReview);
        $groupedVars['all'] = [
            'repeating'  => $allVars->filter(fn($v) => ($v->occurrences ?: 1) > 1),
            'standalone' => $allVars->filter(fn($v) => ($v->occurrences ?: 1) <= 1),
        ];

        return view('template-editor', [
            'template'     => $template,
            'readiness'    => $template->readiness_score ?? 0,
            'pending'      => $pending,
            'needs_review' => $needsReview,
            'approved'     => $approved,
            'rejected'     => $rejected,
            'groupedVars'  => $groupedVars,
        ]);
    }

    // ── AJAX-compatible variable actions ─────────────────────────────
    // Each action detects whether the request expects JSON (AJAX fetch from Alpine.js)
    // or HTML (fallback form POST), and responds accordingly.
    // The AJAX path preserves scroll position — the client never redirects.

    public function approveVariable(Template $template, TemplateVariable $variable): JsonResponse|RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $variable->update(['approval_status' => 'approved']);
        $counts = $this->syncReadiness($template); // 1 query: counts + readiness in one shot

        if (request()->expectsJson()) {
            return response()->json([
                'success'   => true,
                'status'    => 'approved',
                'label'     => $variable->label,
                'message'   => '"' . $variable->label . '" approved.',
                'counts'    => $counts,
                'readiness' => $template->readiness_score,
            ]);
        }

        return back()->with('toast', '"' . $variable->label . '" approved.');
    }

    public function rejectVariable(Template $template, TemplateVariable $variable): JsonResponse|RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $variable->update(['approval_status' => 'rejected']);
        $counts = $this->syncReadiness($template);

        if (request()->expectsJson()) {
            return response()->json([
                'success'   => true,
                'status'    => 'rejected',
                'label'     => $variable->label,
                'message'   => '"' . $variable->label . '" rejected.',
                'counts'    => $counts,
                'readiness' => $template->readiness_score,
            ]);
        }

        return back()->with('toast', '"' . $variable->label . '" rejected.');
    }

    public function undoVariable(Template $template, TemplateVariable $variable): JsonResponse|RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $variable->update(['approval_status' => 'pending']);
        $counts = $this->syncReadiness($template);

        if (request()->expectsJson()) {
            return response()->json([
                'success'   => true,
                'status'    => 'pending',
                'label'     => $variable->label,
                'message'   => '"' . $variable->label . '" moved back to pending.',
                'counts'    => $counts,
                'readiness' => $template->readiness_score,
            ]);
        }

        return back()->with('toast', '"' . $variable->label . '" moved back to pending.');
    }

    public function updateVariable(Request $request, Template $template, TemplateVariable $variable): JsonResponse|RedirectResponse
    {
        $this->authorizeWorkspace($template);
        $this->authorizeVariable($template, $variable);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'label'       => ['required', 'string', 'max:100'],
            'type'        => ['required', 'string', 'in:text,date,number,currency,email,phone,address,select'],
            'is_required' => ['boolean'],
        ]);

        if ($validator->fails()) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
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

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'label'   => $variable->label,
                'type'    => $variable->type,
                'message' => 'Field "' . $variable->label . '" updated.',
            ]);
        }

        return back()->with('toast', 'Field "' . $variable->label . '" updated.');
    }

    public function approveAll(Template $template): JsonResponse|RedirectResponse
    {
        $this->authorizeWorkspace($template);

        $template->variables()->where('approval_status', 'pending')->update(['approval_status' => 'approved']);
        $counts = $this->syncReadiness($template);

        if (request()->expectsJson()) {
            return response()->json([
                'success'   => true,
                'message'   => 'All pending variables approved.',
                'counts'    => $counts,
                'readiness' => $template->readiness_score,
            ]);
        }

        return back()->with('toast', 'All pending variables approved.');
    }

    public function updateVariableMode(Request $request, Template $template, TemplateVariable $variable): JsonResponse|RedirectResponse
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

        $modeLabel = TemplateVariable::MODE_LABELS[$mode] ?? $mode;

        if (request()->expectsJson()) {
            return response()->json([
                'success'    => true,
                'value_mode' => $mode,
                'message'    => '"' . $variable->label . '" set to "' . $modeLabel . '".',
            ]);
        }

        return back()->with('toast', '"' . $variable->label . '" updated to "' . $modeLabel . '".');
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

    /**
     * Compute all approval counts in ONE GROUP BY query, then derive and persist the readiness
     * score from the same result.
     *
     * OPTIMIZATION: Previously `syncReadiness()` ran 2 separate count queries (total + approved)
     * AND `getCounts()` ran a third GROUP BY query — totalling 3 queries per approve/reject/undo.
     * This single method replaces both with 1 query, reducing DB round-trips by 66% per action.
     */
    private function syncReadiness(Template $template): array
    {
        $rows = $template->variables()
            ->selectRaw('approval_status, COALESCE(needs_review, 0) as nr, count(*) as cnt')
            ->groupBy('approval_status', 'nr')
            ->get();

        $pending     = 0;
        $needsReview = 0;
        $approved    = 0;
        $rejected    = 0;

        foreach ($rows as $row) {
            $cnt = (int) $row->cnt;
            match ($row->approval_status) {
                'approved' => $approved    += $cnt,
                'rejected' => $rejected    += $cnt,
                'pending'  => $row->nr ? ($needsReview += $cnt) : ($pending += $cnt),
                default    => null,
            };
        }

        $total = $pending + $needsReview + $approved + $rejected;
        $score = $total > 0 ? (int) round(($approved / $total) * 100) : 0;

        $template->update(['readiness_score' => $score]);
        $template->readiness_score = $score;

        return [
            'pending'      => $pending,
            'needs_review' => $needsReview,
            'approved'     => $approved,
            'rejected'     => $rejected,
            'total'        => $total,
        ];
    }
}
