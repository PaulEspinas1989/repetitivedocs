# RepetitiveDocs — Inline Variable Review

## Purpose

The inline variable review experience allows users to approve or reject AI-detected variable suggestions **without the page reloading or scrolling to the top**.

Every approve/reject action updates the card in-place. The user stays exactly where they were in the list and can continue reviewing the next suggestion immediately.

---

## The No-Scroll-Jump Rule

**This is a hard product rule:**

Approving or rejecting a variable must NEVER:
- Reload the page
- Scroll the user to the top
- Move the user's viewport
- Dump a result summary at the top of the page

It must ALWAYS:
- Update the card in-place
- Keep scroll position stable
- Show inline feedback on the same card
- Update tab badge counts quietly

---

## How It Works (Technical)

### Frontend (Alpine.js)
Each variable card (`partials/variable-card.blade.php`) and row (`partials/variable-row.blade.php`) is wrapped in `x-data` with:
- `status` — mirrors the current `approval_status` (pending/approved/rejected)
- `loading` — which action is in progress (null/approving/rejecting/undoing)
- `error` — inline error message if action fails

When the user clicks Approve/Reject/Undo:
1. `loading` is set immediately → shows inline loading state
2. `fetch()` sends POST to the endpoint with `Accept: application/json`
3. On success: `status` updates → card changes appearance in-place
4. Event `rd-status-change` is dispatched with updated counts
5. Template editor listens and updates tab badge counts reactively

### Backend (Laravel)
All `TemplateEditorController` action methods detect `request()->expectsJson()`:
- When JSON is expected (AJAX fetch): return JSON with `success`, `status`, `label`, `counts`, `readiness`
- When HTML is expected (fallback form POST): return `back()` as before (backward compat)

---

## Suggestion Statuses

| Status | What it means |
|--------|--------------|
| `pending` | AI detected this field, user hasn't reviewed it yet |
| `approved` | User approved — field appears in the fillable form |
| `rejected` | User rejected — field is hidden, ignored in generation |
| `needs_review` | AI flagged as uncertain — shown in its own tab |

The `needs_review` flag on `template_variables` separates uncertain candidates from confident detections. They still start as `pending` approval status but are routed to the Needs Review tab.

---

## Template Editor Tabs

| Tab | Content | Badge color |
|-----|---------|------------|
| Pending | Confident AI detections awaiting review | Blue |
| Needs Review | Uncertain AI detections (low confidence) | Warning/orange |
| Approved | Approved fields — ready for generation | Green |
| Rejected | Rejected fields | Red |
| All | All fields combined | Slate |

Counts on tabs update immediately after each approve/reject via the `rd-status-change` custom event.

---

## Approve Behavior

1. User clicks **Approve**
2. Card shows "Adding this field…" loading state
3. Action buttons disabled during request
4. On success: card shows Approved badge + Undo action
5. Tab counts update: Pending−1, Approved+1
6. Readiness score updates in top bar
7. Toast fires: `"Field Name" approved.`

---

## Reject Behavior

1. User clicks **Reject**
2. Card shows "Ignoring this suggestion…" loading state
3. On success: card shows Rejected badge + Undo action
4. Tab counts update: Pending−1, Rejected+1
5. Toast fires: `"Field Name" rejected.`

---

## Undo Behavior

1. User clicks **Undo** (available after approve or reject)
2. Card shows "Updating…" loading state
3. On success: card returns to Pending state with Approve/Reject buttons
4. Tab counts update accordingly
5. Scroll position preserved

---

## Error Handling

If the server returns an error or the network fails:
- Card stays in its current state
- An inline error bar appears at the top of the card
- User can dismiss the error and retry
- No page reload occurs

---

## Needs Review Tab

Variables with `needs_review = true` appear in a separate tab with:
- Warning banner explaining why they need review
- Same inline approve/reject/undo behavior
- Extra context: `needs_review_reason` explaining AI uncertainty

When a needs_review variable is approved or rejected, it behaves exactly like a normal variable. The `needs_review` flag only controls which tab it initially appears in.

---

## Accessibility

- All action buttons have `aria-label` with the variable name
- Status changes use `aria-live="polite"` on status spans
- Focus stays near the card after action (no focus jump to top)
- Loading states include text (not spinner-only)
- Error messages are announced via `role="alert"` + `aria-live="polite"`
- Tab badges update are screen-reader friendly

---

## Testing Checklist

- [ ] Approve a variable in the Pending tab — card updates inline
- [ ] Page does NOT scroll to top after approve
- [ ] Pending count decreases, Approved count increases (tab badges)
- [ ] Reject a variable — card updates inline with Rejected state
- [ ] Undo after approve — card returns to Pending
- [ ] Undo after reject — card returns to Pending
- [ ] Network error → inline error bar appears, card stays put
- [ ] Approve same card twice (fast double-click) → second click ignored (loading guard)
- [ ] Open Needs Review tab → uncertain variables shown with warning context
- [ ] Approve a needs_review variable → counts update, card goes to Approved state
- [ ] Automation Map inline rows work the same way (variable-row.blade.php)
- [ ] Mobile: cards remain stable, scroll position preserved
- [ ] Keyboard: can trigger approve/reject, focus stays near card
