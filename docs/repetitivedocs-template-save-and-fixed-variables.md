# RepetitiveDocs — Template Save Modes & Fixed Variables

## 1. Generate Once vs Save as Template

After uploading a document and reviewing detected fields, the user chooses:

| Mode | What it does | Where it appears |
|------|-------------|-----------------|
| **Generate once** | Fill and download immediately. Template not in library. | One-time upload |
| **Save as template** | Template saved to library. Reuse as many times as needed. | My Templates |

The choice screen appears after the Automation Map (or after approving all fields).

**Template save_mode values:**
- `draft` — AI scan complete, decision not yet made
- `one_time` — user chose Generate Once
- `saved_template` — user saved to library

**is_saved_template** — boolean, true once user explicitly saves.

---

## 2. Template Save States

| Status | Description |
|--------|-------------|
| `draft` | Just created from AI scan. Default state. |
| `one_time` | User chose Generate Once. |
| `saved_template` | Saved to library. Reusable. |

A one-time template can be converted to saved after generation from the generation result screen ("Save as Template" CTA).

---

## 3. Variable Value Modes

Each approved template variable has a `value_mode` that controls how it behaves during generation.

| Mode | User label | Behavior |
|------|-----------|---------|
| `ask_each_time` | Ask every time | Always shown in form. User must fill. |
| `default_editable` | Use as default | Form pre-filled with `default_value`. User can edit. |
| `fixed_hidden` | Keep as fixed | Hidden from form. `fixed_value` applied automatically. |

**Default**: All new variables start as `ask_each_time`.

---

## 4. Fixed Variable Behavior

When `value_mode = fixed_hidden`:
- The field is **hidden** from the fillable form (`show_when_fixed = false` by default)
- `fixed_value` is automatically used in every generation
- User can view/edit fixed fields from the Fixed Fields summary card (expandable)
- User can use a one-time override without changing the template setting
- User can manage from Template Editor or Fixed Fields Review

**Fixed value traceability:**
- `fixed_value_set_by_user_id` — who set it
- `fixed_value_set_at` — when it was set
- `fixed_value_set_by_generation_id` — which generation the value came from

---

## 5. Default Variable Behavior

When `value_mode = default_editable`:
- Field appears in form with `default_value` pre-filled
- User can edit for that generation
- Edited value only applies to the current document unless user saves it as new default
- If user doesn't edit, `default_value` is used

---

## 6. Ask-Every-Time Behavior

When `value_mode = ask_each_time` (default):
- Field appears normally in every generation form
- Required if `is_required = true`
- No pre-fill

Best for: recipient names, dates, amounts, reference numbers, anything that changes every document.

---

## 7. Fixed Fields Review Flow

Triggered after:
- First generation from a saved template
- User clicks "Review saved answers" on generation result
- User navigates to `/templates/{id}/fixed-fields`

**Screen:** "Save answers for next time"

For each variable, user sees:
- Field name
- Value used in last generation
- AI suggestion (ask / default / fixed)
- AI reason
- Sensitive flag (if personal info detected)
- Choice buttons: Ask every time / Use as default / Keep as fixed
- Value input for fixed/default modes

After saving:
- `value_mode` updated per variable
- `fixed_value` / `default_value` saved
- `user_confirmed_mode = true`
- `fixed_fields_reviewed = true` on template

---

## 8. Fillable Form Behavior

On loading:
1. Load `approvedVariables`
2. Filter: `formVars` = non-fixed (ask + default)
3. Filter: `fixedVars` = fixed_hidden
4. Show fixed fields summary card if `fixedVars` non-empty
5. Show form fields only for `formVars`
6. Pre-fill `default_editable` fields with `default_value`

Fixed summary card (expandable):
- "Loopi already filled 3 fixed fields"
- List: Position of Recipient = MAYOR, etc.
- "Use a different value this time" → shows override input
- "Manage fixed fields" → links to Fixed Fields Review

One-time override:
- `overrides[var_name]` submitted with form
- Does NOT change `fixed_value` on template
- Applied only to this generation

---

## 9. Template Editor Behavior

Each variable card in the editor shows:
- Approval status (pending / approved / rejected)
- Value mode badge (Fixed / Default / [none])
- Pages where variable appears
- In edit mode: value mode selector + fixed/default value input

Value mode selector buttons:
- Ask every time
- Use as default
- Keep as fixed

Saving mode submits `POST /templates/{id}/variables/{var}/mode` with `value_mode` + `fixed_value`.

---

## 10. Generation Value Priority

Values resolved in this order (highest wins):

1. One-time override (`overrides[name]` in form)
2. User-submitted form value (`fields[name]`)
3. Fixed value (`fixed_value` when `value_mode = fixed_hidden`)
4. Default value (`default_value` when `value_mode = default_editable`)
5. Empty → validation error if required

Handled by `GenerationValueResolverService::resolve()`.

---

## 11. Generation Value Traceability

`generated_document_values` table stores per-field records for every generation:

| Column | Description |
|--------|-------------|
| `final_value_used` | The value actually in the document |
| `submitted_value` | What the user typed (null if fixed/default) |
| `value_source` | user_input / fixed_value / default_value / one_time_override / bulk_row / portal_submission |
| `value_mode_at_generation` | Snapshot of mode at time of generation |
| `was_fixed_at_generation` | Boolean |
| `was_default_at_generation` | Boolean |

Past records never change. If user changes fixed_value later, only future generations are affected.

---

## 12. AI Suggestions for Value Modes

After first generation, `GenerationValueResolverService::suggestModes()` returns suggested mode per variable.

**Suggested as fixed:** position, title, office, municipality, province, signatory, organization  
**Suggested as default:** common location, common department, common payment terms  
**Suggested as ask-every-time:** recipient name, date, amount, reference number, contact info, personal info

AI suggestions are shown in Fixed Fields Review with explanation text. User can override any suggestion.

Sensitive field detection: email, phone types + label keywords (name, email, phone, address, id, birthdate, bank, salary, password, tax). Sensitive fields show a warning before allowing fixed mode.

---

## 13. Bulk Generation Behavior (planned)

- `fixed_hidden` variables: no mapping required; automatically applied to every row
- `default_editable` variables: mapping optional; spreadsheet value overrides default if mapped
- `ask_each_time` required variables: must be mapped

Conflict: if spreadsheet has a column matching a fixed variable → prompt user to keep fixed or use spreadsheet values.

---

## 14. Portal Behavior (planned)

Portal Builder field visibility per variable:
- `Ask recipient` — shown in portal form
- `Pre-fill and allow edit` — shown pre-filled
- `Hide and use fixed/default value` — hidden from portal, applied on generation

`fixed_hidden` variables default to hidden.
`ask_each_time` variables default to ask recipient.
`default_editable` variables default to pre-fill.

Hidden fixed values must not appear in public portal HTML.

---

## 15. Sensitive Data Warnings

Before allowing a field to be set as `fixed_hidden`:
- If `looksLikeSensitive()` returns true, show warning:
  > "This looks like personal information. Only keep it fixed if it should appear in every future document from this template."
- User must confirm before saving
- `is_sensitive_flag = true` stored on variable

---

## 16. Plan Limits

| Feature | Free | Starter | Pro | Business |
|---------|------|---------|-----|----------|
| Generate Once | ✅ | ✅ | ✅ | ✅ |
| Save as Template | ✅ (limit 3) | ✅ (limit 10) | ✅ (limit 50) | ✅ (limit 200) |
| Fixed variables | ✅ | ✅ | ✅ | ✅ |
| Default variables | ✅ | ✅ | ✅ | ✅ |
| AI suggestions for modes | ❌ | ❌ | ✅ | ✅ |
| Audit log for fixed changes | ❌ | ❌ | ❌ | ✅ |
| Portal hidden fixed fields | ❌ | ✅ | ✅ | ✅ |
| Bulk fixed field handling | ❌ | Basic | Full | Full |

---

## 17. Permissions

| Role | Can manage fixed/default modes |
|------|-------------------------------|
| Owner / Admin | ✅ |
| Editor | ✅ (if permission set) |
| Viewer | ❌ |
| Portal recipient | ❌ (never sees hidden fixed values) |

---

## 18. Testing Checklist

- [ ] Upload → choose Generate Once → form shows → generates → result shows "Save as Template" CTA
- [ ] Upload → choose Save as Template → editor → approve → generate → "Review saved answers" CTA
- [ ] Fixed Fields Review → set Position = fixed → next generation hides it from form
- [ ] Generated document still contains fixed value in correct position
- [ ] Default editable → form pre-filled → user can change → value saved for this doc only
- [ ] One-time override → fixed value overridden for one doc → template unchanged
- [ ] Grouped variable fixed value → all placements in document updated
- [ ] Sensitive field warning shown before fixing email/phone/name
- [ ] `generated_document_values` table has correct source for each field
- [ ] Changing fixed value later → future docs use new value, past docs unchanged
- [ ] Tenant isolation: workspace A cannot access workspace B fixed values
- [ ] Mobile: Fixed fields summary card works on mobile, Fixed Fields Review works on mobile
- [ ] All buttons have data-loading-text branded copy
