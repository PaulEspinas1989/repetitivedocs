# RepetitiveDocs TEST Deployment QA Rule

## What is TEST?

`TEST` is the single word that triggers a full deployment QA sweep on RepetitiveDocs.com.

When you type `TEST`, Claude Code must immediately:
1. Find the most recent deployed or implemented change
2. Map every file, route, screen, userflow, and service it touches
3. Inspect all direct and branched impacts
4. Run automated checks and code inspections
5. Fix safe bugs immediately
6. Report findings in the standard TEST report format

**TEST is not a unit test command.** It is a complete QA gate that runs before any deployment is considered done.

---

## When to Run TEST

Run TEST:
- After every new feature is implemented
- After every bug fix is deployed
- After every migration is run on production
- After every AI prompt change
- After every document generation change
- After every variable detection or grouping change
- After every UI/UX change
- After any dependency update
- Before any major user demo or release
- When something feels off but you're not sure what

---

## What Must Be Inspected

### Phase 1 — Identify the Change
- `git log --oneline -10`
- `git show --stat HEAD`
- `git diff --name-only HEAD~1 HEAD`
- Working tree uncommitted changes
- Changed files, migrations, routes, views, services, models, jobs

### Phase 2 — Classify
- Type: feature / bug fix / migration / UI / AI / generation / variable / portal / billing / etc.
- User role affected
- Plan tier affected
- Workspace/tenant impact

### Phase 3 — Impact Map
Every TEST must produce an impact map covering:
- Directly changed files
- Directly impacted routes and endpoints
- Directly impacted screens
- Branched screens and userflows also affected
- Database tables touched
- Jobs/queues touched
- AI credits touched
- Emails/notifications touched
- Plan gates touched
- Mobile/responsive impact
- Security/tenant isolation impact

### Phase 4 — Smoke Test
- App boots without fatal error
- Login/register routes work
- Dashboard loads
- No critical environment config broken
- `php artisan route:list` succeeds
- `php artisan migrate:status` shows all migrations ran

### Phase 5 — Feature QA
For the changed feature:
- Happy path works end-to-end
- Empty state is handled
- Loading state shows branded feedback (not "Loading…")
- Success state gives clear confirmation
- Error state gives clear recovery options
- Validation catches all bad input
- Auth/permissions enforced
- Tenant isolation verified
- Plan gate enforced

### Phase 6 — Regression Test
Test ALL of these flows every TEST (code-inspect if live testing unavailable):

| Flow | Key Checks |
|------|-----------|
| Auth | Login, register, forgot password, email verify |
| Upload | File validation, size limits, success/failure states |
| AI Scan | Loading screen, variables detected, credits charged, failure refund |
| Variable grouping | Repeated vars grouped, mayor/signatory detected, edit-once works |
| Template Editor | Variables listed, approval flow, smart tools lock, preflight |
| Fillable Form | One field per canonical variable, validation, grouped update |
| Document generation | PDF/DOCX fidelity, no artifacts, correct fonts/alignment |
| Bulk generation | CSV/XLSX mapping, validation, progress, ZIP download |
| Portal flow | Create, public/private, submission, inbox |
| AI Credits | Balance shown, low-credit warning, hard stop on depletion |
| Plan/upgrade | Locks match plan rules, upgrade modals route correctly |
| Brand Kit | Logo/color upload, plan gate |
| Team | Invite, roles, Business plan gate |
| Notifications | Bell, notification center, click routing |
| Search | Global search, empty/loading states |
| Settings | Profile, workspace, billing, security |

### Phase 7 — UI/UX
- Layout consistent with RepetitiveDocs design system
- All loading states use branded copy (not "Loading…")
- No "null", "undefined", "error" visible to users
- No raw variable keys or AI confidence scores visible
- No broken images
- Loopi used appropriately
- Copy is plain English, not technical jargon

### Phase 8 — Responsive
Check at: 1440px · 1280px · 768px · 430px · 390px
- No horizontal overflow
- Buttons ≥ 44px tall on mobile
- Forms stack properly
- Tables scroll or convert to cards
- Modals work on mobile

### Phase 9 — Accessibility
- All inputs have labels
- Icon-only buttons have `aria-label`
- Focus states visible
- Modals trap focus
- Errors tied to fields
- `aria-live` on status changes
- Keyboard navigable

### Phase 10 — Security + Tenant Isolation
MANDATORY. Never skip.
- Every DB query scoped to `workspace_id`
- Every controller verifies workspace ownership
- No IDOR vulnerabilities
- Private files not publicly accessible
- No sensitive data in logs
- AI keys server-side only
- CSRF tokens on all forms
- No XSS from user-controlled content

### Phase 11 — Plan Limits + AI Credits
MANDATORY. Never skip.
- Frontend locks match backend enforcement
- Backend hard-stops on limits (not just frontend)
- AI credits reserved before call, refunded on failure
- Credits cannot go negative (unless paid overage allowed)
- Retry logic doesn't double-charge

### Phase 12 — Document Generation
If generation changed:
- PDF fidelity: font size, weight, color, alignment preserved
- DOCX fidelity: formatting preserved via TemplateProcessor
- No error text, UI labels, variable keys in output file
- Signature/name blocks correctly centered and clean
- Erasure rect covers only the text line, not adjacent content
- Long values trigger preflight warning, not silent overflow
- Generated file scoped to correct workspace

### Phase 13 — Email + Notifications
If emails/notifications changed:
- Emails queued (not blocking)
- Templates render correctly
- No sensitive document content in email
- Links route correctly
- Unsubscribe/preferences respected

### Phase 14 — Performance
- No N+1 queries in critical paths
- Heavy work (AI calls, PDF generation, bulk jobs) in queues
- New query patterns have appropriate indexes
- No unbounded queries

### Phase 15 — Code Quality
- No `dd()`, `dump()`, `var_dump()` in committed code
- No `console.log()` in production frontend
- No hardcoded secrets or API keys
- No commented-out debug blocks
- No dead/unused code left behind
- Laravel conventions followed
- Services thin and focused

---

## What Must Be Fixed

**Fix immediately (same session):**
- Runtime errors (500, TypeErrors, fatal exceptions)
- Security vulnerabilities (IDOR, missing auth, data leaks)
- Tenant isolation violations
- AI credit double-charging
- Error text appearing in generated documents
- Division by zero / null pointer exceptions

**Document and flag for human review:**
- Plan limit logic that needs product decision
- AI prompt tuning that needs testing with real documents
- Performance issues that require schema changes
- Mobile UX issues that need design input

---

## What Must Be Reported

Every TEST outputs this report:

```
# RepetitiveDocs TEST Deployment QA Report

## Summary
- Change tested: [description]
- Change type: [type]
- Overall status: Passed | Passed with fixes | Failed | Needs human review
- Highest risk area: [area]

## Recent Change Detected
[commit hash, files, inferred feature]

## Impact Map
[direct files · direct routes · direct screens · branched userflows · DB · jobs · AI · billing]

## Tests and Checks Performed
[automated tests · build/lint · manual code inspection · responsive · accessibility · security]

## Issues Found
For each issue:
- Severity: Critical / High / Medium / Low
- Area
- Description
- Root cause
- User impact
- Fix status: Fixed | Flagged for review | Accepted risk

## Fixes Applied
[file changed · what was fixed · why safe]

## Regression Results
[core flows checked · passed/failed]

## Security and Tenant Isolation Review
[workspace scoping · file access · permission status · risks]

## Plan Limit / AI Credit Review
[gates checked · credit behavior · issues]

## UI/UX and Responsive Review
[desktop · tablet · mobile · states · accessibility]

## Remaining Risks
[needs human review · untestable · missing coverage · external deps]

## Recommended Next Work
[immediate fixes · tests to add · refactors · QA automation]

## Final Verdict
SAFE TO DEPLOY | SAFE AFTER HUMAN REVIEW | NOT SAFE TO DEPLOY | NEEDS MORE TESTING
```

---

## What Must Never Be Skipped

| Rule | Reason |
|------|--------|
| Tenant isolation check | Cross-tenant data leaks destroy trust |
| Security review | One IDOR can expose all user documents |
| Plan limit enforcement check | Revenue leakage and abuse |
| AI credit check | Financial and provider cost impact |
| Document generation output check | Core product fidelity — this is what users pay for |
| Branched userflow regression | Changes ripple to unexpected flows |
| Mobile check | Significant user base on mobile |
| Loading state check | Brand standard — every button must acknowledge the click |

---

## Core RepetitiveDocs Screens (Reference)

| Screen | Route Name |
|--------|-----------|
| Landing | `home` |
| Register | `register` |
| Login | `login` |
| Dashboard | `dashboard` |
| Upload Document | `upload` |
| AI Loading | `ai-loading` |
| Automation Map | `automation-map` |
| Template Editor | `templates.editor` |
| Fillable Form | `fillable-form` |
| Generation Result | `generation-result` |
| Download | `generated-documents.download` |
| Template Variables | `templates.variables` |

Missing from routes (planned/future):
- Template Gallery (`template-gallery`)
- Bulk Generate (`bulk.upload`)
- Portals (`portals`)
- Generation History (`history`)
- Brand Kit (`brand-kit`)
- Settings (`settings`)
- Pricing/Billing (`pricing`)
- AI Credits (`ai-credits`)
- Notifications (`notifications`)
- Search (`search`)

---

## Test Environment Notes

- Local PHP: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- Local DB: Not connected (Supabase is production-only)
- Migrations 000011–000015 are Pending locally — all Ran on production server
- `ExampleTest` fails locally due to missing Vite manifest — pre-existing, not caused by recent changes
- Production server: `ssh root@139.162.61.79`
- Deploy: `.\deploy-production.bat` (Windows) or SSH manual commands

---

*This document is auto-enforced. When the user says `TEST`, Claude Code runs this protocol immediately.*
