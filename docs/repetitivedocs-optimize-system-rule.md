# RepetitiveDocs OPTIMIZE System Optimization Rule

## What is OPTIMIZE?

`OPTIMIZE` is the single word that triggers a full system optimization sweep on RepetitiveDocs.com.

When you type `OPTIMIZE`, Claude Code must immediately:
1. Inspect the most recent deployed or implemented change
2. Establish a performance, quality, and UX baseline
3. Map every file, route, screen, userflow, and service it touches
4. Identify safe, high-impact optimization opportunities
5. Apply improvements without breaking correctness, security, or product behavior
6. Measure improvements where possible
7. Run TEST and produce the OPTIMIZE report

**OPTIMIZE is not code formatting.** It is a comprehensive engineering quality sweep.

---

## When to Run OPTIMIZE

Run OPTIMIZE:
- After every major feature implementation
- After every significant UI/UX change
- When the system feels slow or bloated
- Before major releases or demos
- After adding new database queries or relationships
- After adding new AI calls or prompts
- After adding new document generation logic
- After noticing N+1 queries or repeated work
- When code duplication becomes a maintainability concern
- Periodically as a routine system health check

---

## What Must Be Inspected

### Phase 1 — Scope
- `git log --oneline -10` to see recent changes
- `git show --stat HEAD` to see changed files
- Identify the feature area, userflows, and services impacted

### Phase 2 — Baseline
Collect before making changes:
- DB query count in critical paths (N+1 risks)
- AI call count and token usage
- Collection/array operations in views (should be in controllers)
- Duplicate business logic across files
- Missing eager loading
- Synchronous heavy work
- Missing pagination
- Full-page form POSTs that could be AJAX
- Missing loading/empty states
- Accessibility gaps
- Mobile overflow or touch issues
- Dead code, magic strings, console.log

### Phase 3 — Impact Map
List all:
- Directly impacted files
- Directly impacted routes/endpoints
- Directly impacted screens
- Branched screens and userflows
- Database tables and query patterns
- Jobs/queues touched
- AI calls touched
- Storage operations touched

---

## What Must Be Optimized

### Database
- Consolidate N+1 queries
- Add eager loading (`->with()`, `->load()`)
- Select only needed columns (`->select([...])`)
- Add missing indexes via migrations (only if clearly beneficial)
- Paginate large unbounded queries
- Move collection filters from views to controllers

### Backend
- Consolidate duplicate service logic
- Extract to query scopes
- Move heavy work to queue jobs
- Use transactions for multi-step writes
- Cache safe reference data (never cache tenant-sensitive data unsafely)
- Batch operations with `chunk()`

### Frontend / Alpine.js
- Eliminate inline form POSTs that cause scroll-to-top (convert to Alpine fetch())
- Remove duplicated Alpine `x-data` methods across partials
- Use `x-show` instead of `x-if` for frequently-toggled elements (avoids DOM destruction)
- Manage focus after dynamic state changes
- Keep `x-data` objects small — defer heavy data to server responses
- Dispatch typed, consistent events for cross-component communication

### AI
- Trim prompt bloat — use compact, directive-first language
- Reserve stronger models for complex work, haiku for simple tasks
- Reduce `maxTokens` to realistic ceilings (not over-provisioned)
- Cache deterministic AI results per document version/hash
- Guard against `x-if` DOM destruction in loading states (use `x-show`)

### UX
- Inline approve/reject without page reload
- Preserve scroll position on list actions
- Show loading states within 100–200ms of user action
- Manage focus after state transitions
- Add `aria-live`, `aria-selected`, `role="tab"`, `aria-atomic` where missing

### Security
- Centralize tenant workspace scoping
- Centralize plan gate checks
- Validate all JSON/event data before trusting
- Inject CSRF tokens via data attributes where meta tag reliance is fragile

---

## What Must NOT Be Changed

- Product behavior (unless clearly broken)
- Security, tenant isolation, plan limits, AI credit tracking
- Document generation fidelity (font, alignment, casing, prefix/suffix)
- Loopi branding, pantones, loading state copy
- Accessibility attributes that exist and work correctly
- Tests that are passing
- Public routes (unless all references updated simultaneously)
- Production environment configuration

---

## Metrics to Check

| Metric | How to measure |
|--------|---------------|
| DB queries per request | Laravel Debugbar or manual query logging |
| AI tokens per analysis | Log `input_tokens` + `output_tokens` from AI provider |
| Bundle size | `npm run build` output |
| Duplicate code | Manual inspection, grep for repeated patterns |
| Test pass rate | `php artisan test` |
| Routes intact | `php artisan route:list` |
| Accessibility | Manual review, WAVE/axe tools |
| Mobile overflow | Browser DevTools responsive mode |

---

## Tests to Run After OPTIMIZE

```bash
# Backend
php artisan test
php artisan route:list
php artisan migrate:status

# Frontend
npm run build
npm run lint (if available)
```

Also run the full TEST rule after OPTIMIZE.

---

## OPTIMIZE Report Format

```
# RepetitiveDocs OPTIMIZE System Optimization Report

## Summary
- Scope:
- Biggest improvement:
- Highest remaining risk:
- Final verdict:

## Baseline Findings
- DB queries before:
- AI calls before:
- Code duplications found:
- UX/accessibility gaps:

## Optimizations Applied
For each:
- File: line range
- What changed
- Why safe
- Expected benefit
- Risk level

## Metrics (before/after)
- Query count: X → Y
- Duplicate code blocks: X → Y
- Accessibility issues: X → Y

## TEST Results
- TEST run: Yes/No
- Tests/build: pass/fail
- Regressions: none/list

## Remaining Risks
- List what still needs work

## Final Verdict
OPTIMIZED AND SAFE | OPTIMIZED WITH FOLLOW-UP | NOT SAFE
```

---

## RepetitiveDocs Priority Optimization Areas

| Area | Common Issues | Priority |
|------|--------------|---------|
| Variable review AJAX | Duplicated Alpine doAction() across partials | High |
| AI detection | Prompt bloat, repeated calls, over-provisioned maxTokens | High |
| Document generation | Style snapshot reuse, temp file cleanup | High |
| Template editor | syncReadiness() double-count queries | High |
| Collection filters | Moved to Blade views instead of controllers | Medium |
| Accessibility | Missing role="tab", aria-selected, focus management | Medium |
| DB indexes | Missing indexes for workspace+status combinations | Medium |
| Bulk generation | No chunking, not in queue | High (when built) |
| Portal forms | Not built yet | Future |
| AI credits | Not fully implemented yet | Future |
