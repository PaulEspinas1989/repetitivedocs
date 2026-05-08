# RepetitiveDocs.com — Claude Code Instructions

## Project Overview

RepetitiveDocs.com is an AI-assisted document personalization SaaS.

**Core promise:** Upload once. Personalize forever.

**Stack:** Laravel 13 / PHP 8.3 · Blade + Alpine.js + Tailwind v4 · Supabase PostgreSQL · Linode VPS · Nginx + PHP-FPM 8.3 · Anthropic Claude API · FPDF / PhpOffice · PayMongo

---

## TEST — Deployment QA Rule

**This is a permanent, mandatory quality gate for RepetitiveDocs.com.**

### When the user says `TEST`, immediately execute the full Deployment QA Sweep.

Do not ask what to test. Do not ask for clarification unless it is genuinely impossible to infer.

Assume `TEST` means:
> Inspect, test, debug, optimize, and report on the most recently deployed or implemented changes and everything they affected.

### What TEST must do (non-negotiable):

1. **Identify** the most recent change (git log, git diff, working tree)
2. **Classify** the change type (feature, fix, migration, UI, AI, generation, etc.)
3. **Map** direct AND branched impact (files, routes, screens, userflows, DB, jobs, AI, billing)
4. **Smoke test** the app (routes load, no fatal errors, builds pass)
5. **Feature-specific QA**: happy path, empty state, error state, validation, permissions
6. **Regression test** all connected flows (auth, upload, AI scan, variable grouping, editor, form, generation, bulk, portal, billing, team, notifications, search, settings)
7. **UI/UX check**: layout, loading states, empty states, Loopi usage, branded copy
8. **Responsive check**: Desktop 1440, Laptop 1280, Tablet 768, Mobile 430
9. **Accessibility check**: labels, focus states, keyboard nav, aria-live, contrast
10. **Security + tenant isolation**: workspace scoping, IDOR, auth gates, file access
11. **Plan limits + AI credits**: gates enforced server-side, credits not double-charged
12. **Document generation QA**: fidelity, no error artifacts, signature blocks, DOCX/PDF
13. **Email/notification QA**: queued, correct routing, no data leaks
14. **Performance QA**: no N+1, heavy work queued, indexes present
15. **Code quality**: no dd/var_dump, no console.log, no hardcoded secrets, no dead code
16. **Fix** safe issues immediately. Document risky ones.
17. **Run** `php artisan test`, build checks, route/migration status
18. **Report** using the standard TEST report format

### What TEST must never skip:

- Tenant isolation verification
- Security checks
- Plan limit enforcement
- AI credit behavior
- Document generation output quality
- Mobile/responsive check
- Branched userflow regression

### TEST Report format (always output this):

```
# RepetitiveDocs TEST Deployment QA Report

## Summary
- Change tested:
- Change type:
- Overall status: [Passed | Passed with fixes | Failed | Needs human review]
- Highest risk area:

## Recent Change Detected
## Impact Map
## Tests and Checks Performed
## Issues Found (Severity: Critical / High / Medium / Low)
## Fixes Applied
## Regression Results
## Security and Tenant Isolation Review
## Plan Limit / AI Credit Review
## UI/UX and Responsive Review
## Remaining Risks
## Recommended Next Work
## Final Verdict: [SAFE TO DEPLOY | SAFE AFTER HUMAN REVIEW | NOT SAFE TO DEPLOY | NEEDS MORE TESTING]
```

---

## Architecture Rules

### Multi-tenant / Workspace isolation (MANDATORY)
- Every query MUST be scoped to `workspace_id`
- Every controller must verify workspace ownership before operating on any record
- Never expose records from other workspaces — not in JSON, not in views, not in downloads
- Use `abort(403)` not silent failures for auth violations

### Plan limits (MANDATORY — enforce server-side)
- Free: 3 templates, 5 docs/month, 10 AI credits/month, 5 MB file limit
- Starter: 10 templates, 100 docs/month, 50 AI credits, 25 MB, password portals, bulk ≤50 rows
- Pro: 50 templates, 500 docs/month, 300 AI credits, 100 MB, Brand Kit, Smart Rules, QR, Sheets
- Business: 200 templates, 2000 docs/month, 1500 AI credits, 250 MB, Team, Audit, XLSX
- Enterprise: API keys, Webhooks, SSO, white-label, custom limits

Never trust frontend for plan enforcement. Always validate server-side.

### AI credit rules
- Reserve credits BEFORE calling AI
- Release/refund on failure
- Never go negative unless paid overage is explicitly allowed
- Log every AI call with model, tokens, cost, workspace
- AI keys stay server-side only — never in frontend

### Loading states (MANDATORY — apply to every button and async action)
- Every `<button type="submit">` must have `data-loading-text="..."` attribute
- Use branded copy, never generic "Loading…"
- Use `<x-spinner />`, `<x-skeleton />`, `.rd-*` CSS classes
- See `feedback_loading_states.md` in memory for full rule

### Generated documents (fidelity rules)
- Preserve original font size, weight, color, alignment as much as possible
- Never output UI labels, variable keys, AI confidence scores, or error text in final PDF/DOCX
- Center-aligned text (signature blocks) must stay centered
- Mayor/signatory names must render without artifacts above them
- Erasure rect must cover only the original text line height

---

## Deployment

### Deploy script: `.\deploy-production.bat`
Pushes to GitHub → SSH to Linode → git pull → composer → npm build → view:clear → view:cache → migrate → php-fpm reload → supervisorctl restart

### Server: `root@139.162.61.79` (Linode)
Run server-side commands via: `ssh root@139.162.61.79 "..."`

### PHP path (local): `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`

---

## Code Standards

- Never use `dd()`, `dump()`, `var_dump()` in committed code
- Never hardcode secrets, API keys, or credentials
- Never expose raw AI JSON, confidence decimals, or variable keys to normal users
- Never use `match()` inside Blade `@php` blocks (confuses Blade tokenizer — use if/elseif)
- Never use `{{ '}}' }}` or similar in Blade (use `&#125;&#125;` HTML entities instead)
- Always use `@json()` directive in Blade x-data attributes, never `json_encode()` directly
- Keep controllers thin — business logic in Services
- Use `Validator::make()` not `$request->validate()` when you need to attach session data on failure
- Always eager-load relationships to avoid N+1 (use `->with()` or `->load()`)
- Scope all DB queries to workspace — never query without workspace filter on tenant data

---

## Known Gotchas

- `occurrences ?: 1` not `?? 1` — Laravel casts null int column → 0, and `0 ?? 1 = 0` (wrong)
- `iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', ...)` — use `//IGNORE` flag so empty string doesn't fall back to raw UTF-8
- FPDF cMargin default ≈ 1mm — subclass and set `$this->cMargin = 0` for overlay rendering
- pdftohtml fontspec are global across pages — collect all in first pass before processing text
- Blade `@switch` inside `@php` is fine; `match()` inside `@php` is NOT (brace tokenizer bug)
- `view:clear` must run BEFORE `view:cache` in deploy — otherwise old compiled views survive
- `systemctl reload php8.3-fpm` (graceful) may not flush opcache for in-flight workers — use `restart` when opcache issues are suspected
