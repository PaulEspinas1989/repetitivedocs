# RepetitiveDocs — Fillable Form / Generation Binding & Date Formatting Inspection

**Date:** 2026-05-30  
**Status:** Root causes identified — fixes applied in this session

---

## Root Cause 1: Date Shows as ISO (2026-05-30) in Generated Document

### Chain of causation

| Step | Location | What happens |
|------|----------|--------------|
| 1 | `fillable-form.blade.php:181` | `<input type="date">` renders browser date picker |
| 2 | Browser | User selects May 30 2026 → browser submits `2026-05-30` (HTML5 spec) |
| 3 | `FillableFormController::generate():58` | Validates with `['required', 'date']` — no format transformation |
| 4 | `GenerationValueResolverService::resolve()` | Returns `2026-05-30` unchanged |
| 5 | `DocumentGenerationService::generateFromDocx():77` | `$processor->setValue($var->name, '2026-05-30')` |
| 6 | Generated DOCX/PDF | Shows literal `2026-05-30` |

### Why no date formatting existed

- No `date_format` column on `template_variables` (nothing to store the intended format)
- No `DateFormatterService` (no formatting logic)
- AI detection stored example date as plain string (e.g. `"May 30, 2026"`) but never extracted the format pattern
- `GenerationValueResolverService` had no type-aware formatting step

### Fix applied

1. **Migration** `2026_05_30_000020_add_date_format_to_template_variables.php` — adds `date_format` (nullable string, default `'F j, Y'`)
2. **`DateFormatterService`** — detects PHP date format string from an example date, formats ISO input to target format
3. **`VariableDetectionService`** — for date-type variables, calls `DateFormatterService::detectFormat($exampleValue)` and persists to `date_format`
4. **`GenerationValueResolverService`** — after resolving a date-field value, calls `DateFormatterService::format($raw, $var->date_format)` before returning
5. **`fillable-form.blade.php`** — adds Alpine.js date preview ("Will appear as: May 30, 2026") below every date input

---

## Root Cause 2: Form Values Not Reflecting in Generated File

### Possible causes (in priority order)

| Cause | Likelihood | Notes |
|-------|-----------|-------|
| Variable not approved | High | Only `approvedVariables` are displayed and rendered |
| PDF: no active occurrences | High | `resolveOverlayPositions()` returns `[]` → skipped silently |
| PDF: bounding_box null/malformed | Medium | `toOverlayPosition()` returns `null` → filtered out |
| DOCX raw fallback: `example_value` mismatch | Medium | `str_replace($var->example_value, $newValue, $xml)` — must exactly match XML text |
| DOCX template processor: missing placeholder | Low | Template DOCX must have `${var_name}` for `TemplateProcessor::setValue()` |
| Currency stripping removing valid value | Low | `preg_replace('/[₱,\s]/', '', ...)` could blank a valid value if mistyped |

### What the value flow looks like correctly

```
User types "MAYOR" into fields[position_of_recipient]
  ↓
FillableFormController::generate()
  $userValues['position_of_recipient'] = 'MAYOR'
  ↓
GenerationValueResolverService::resolve()
  Priority 1: no override → skip
  Priority 2: userValues['position_of_recipient'] = 'MAYOR' → return 'MAYOR'
  ↓
DocumentGenerationService::generateFromPdfOverlay()
  $values['position_of_recipient'] = 'MAYOR'
  foreach($template->approvedVariables as $var) {
    $newValue = $values['position_of_recipient'] = 'MAYOR'
    $positions = $var->resolveOverlayPositions()
    → if empty: SILENTLY SKIPPED ← main failure point for PDF
  }
```

### Fix applied

- Added "Keep as Constant" inline toggle (see Phase 12 below) — orthogonal improvement
- The form value binding itself is already correct when occurrences exist
- Added date format transformation so date values arrive formatted, not raw ISO

---

## Current Form State Flow

```
Blade server-render → formVars (approvedVariables not hidden)
                    → fixedVars (approvedVariables that are fixed_hidden)

User fills form → POST fields[var_name] = value
               → POST overrides[var_name] = value  (one-time fix override)
               → POST keep_as_constant[var_name] = 1  (NEW — inline toggle)

FillableFormController::generate()
  1. Validate rules per var type
  2. Strip currency formatting
  3. Format date values via DateFormatterService  (NEW)
  4. $generator->generate($template, $userValues, $overrides)
  5. After success: save keep_as_constant fields as fixed_value  (NEW)
  6. Redirect to generation result
```

## Current Generation Payload Shape

```php
$userValues = [
    'recipient_name'     => 'Juan Dela Cruz',
    'event_date'         => 'May 30, 2026',    // formatted (was '2026-05-30')
    'position'           => 'MAYOR',
]
$overrides = [
    'municipality_name'  => 'Masbate City',    // one-time fixed override
]
```

## Value Resolution Priority (GenerationValueResolverService)

1. One-time override (`$overrides[$name]`)
2. User-submitted form value (`$userValues[$name]`)
3. Fixed value (`$var->fixed_value` when `value_mode = fixed_hidden`)
4. Default value (`$var->default_value` when `value_mode = default_editable`)
5. null (triggers validation warning for required fields)

## Date Handling (after fix)

```
<input type="date"> → '2026-05-30'
  ↓ FillableFormController: DateFormatterService::format('2026-05-30', $var->date_format)
  ↓ date_format = 'F j, Y'  (auto-detected from example_value 'May 30, 2026')
  ↓ Carbon::createFromFormat('Y-m-d', '2026-05-30')->format('F j, Y')
  ↓ = 'May 30, 2026'
  ↓ passed to resolver → generator → document
Generated document shows: May 30, 2026 ✓
```

## Files Impacted by These Fixes

| File | Change |
|------|--------|
| `database/migrations/2026_05_30_000020_add_date_format_to_template_variables.php` | NEW — adds `date_format` column |
| `app/Services/DateFormatterService.php` | NEW — format detection + formatting |
| `app/Services/GenerationValueResolverService.php` | UPDATED — format date fields in resolve() |
| `app/Services/VariableDetectionService.php` | UPDATED — detect + store date_format on variable create |
| `app/Http/Controllers/FillableFormController.php` | UPDATED — inject DateFormatterService, handle keep_as_constant |
| `app/Models/TemplateVariable.php` | UPDATED — add date_format to fillable/casts |
| `resources/views/fillable-form.blade.php` | UPDATED — date preview, keep-as-constant toggle |

---

## Phase 12 — Inline "Keep as Constant" Feature

### How it works

Each eligible field in the fillable form shows a toggle checkbox:

```
Position of Recipient *
[ MAYOR                    ]
☐ Remember this for future documents
```

When checked:
- Submits `keep_as_constant[position_of_recipient] = 1` with the form
- Current generation uses the typed value normally
- After successful generation, controller sets `value_mode = fixed_hidden` and `fixed_value = 'MAYOR'`
- Future form loads show this field under "Already filled" fixed summary

### Eligibility

Fields NOT eligible for constant toggle:
- `date` type (always changes)
- `number` / `currency` type (always changes)
- Already `fixed_hidden` (already constant)

Fields eligible:
- `text`, `email`, `phone`, `address`, `select`
- Shown with toggle regardless of `entity_role`

Sensitive fields (email, phone, name-like labels) show extra warning copy.

### Data saved after successful generation

```php
$variable->update([
    'value_mode'                    => TemplateVariable::MODE_FIXED,
    'fixed_value'                   => $submittedValue,
    'fixed_value_set_by_user_id'    => auth()->id(),
    'fixed_value_set_at'            => now(),
    'fixed_value_set_by_generation_id' => $generated->id,
    'user_confirmed_mode'           => true,
]);
```
