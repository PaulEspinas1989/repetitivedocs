# RepetitiveDocs — Variable Detection & Style-Preserving Generation

## 1. Why Multi-Pass Detection

Documents contain editable values in many places AI can miss with a single text scan:
- **Headers/footers** — repeating organization names, dates, page references
- **Tables** — amounts, quantities, line-item descriptions
- **Signature blocks** — names above titles, ALL CAPS names, "HON." prefixed names
- **Labeled fields** — "Name:", "Position:", "Date:" followed by values
- **Repeated values** — same name appearing 5 times in different formats

The current detection pipeline uses:
1. `pdftohtml -xml` to extract all text with coordinates and font metadata
2. `extractPdfTextElements()` — captures font_size, font_color, font_family, font_weight, source_area (header/footer/body)
3. AI (Claude claude-sonnet-4-6) — semantic detection of all variable candidates including uncertain ones
4. `findAllTextPositions()` — matches AI-detected values to PDF coordinates across ALL pages
5. `createOccurrenceRecords()` — stores one VariableOccurrence per placement with full style + context data

---

## 2. Canonical Variables vs Occurrences

### Canonical Variable (`template_variables` table)
The logical field the user edits once.

Example: `mayor_name` — display name "Mayor Name" — user enters "Maria Santos"

### Variable Occurrence (`variable_occurrences` table)
Each actual placement in the document with its own style, position, prefix, casing.

Example occurrences of `mayor_name`:
- Page 1, signature block: "HON. JUAN DELA CRUZ" — uppercase, bold, centered, 12pt
- Page 2, approval section: "Juan Dela Cruz" — title case, regular, 11pt
- Page 3, footer: "Mayor Juan Dela Cruz" — title case, 10pt

When user enters "Maria Santos":
- Page 1 renders: "HON. MARIA SANTOS" (uppercase, bold, centered)
- Page 2 renders: "Maria Santos" (title case, regular)
- Page 3 renders: "Mayor Maria Santos" (title case, with prefix)

---

## 3. Style Snapshot Rules

Every `VariableOccurrence` stores a `style_snapshot` JSON:
```json
{
  "font_size": 12,
  "font_color": "#000000",
  "font_family": "TimesNewRoman,Bold",
  "font_weight": "bold",
  "text_align": "C"
}
```

And new detection metadata fields:
- `source_area` — body | header | footer | table | signature_block | labeled_field | unknown
- `casing_pattern` — uppercase | titlecase | lowercase | mixed
- `nearby_label` — text of nearest label (e.g. "Municipal Mayor", "Approved by:")
- `detection_source` — pdf_position | ai_occurrence | fallback | user_added

---

## 4. Layout Snapshot Rules

Every occurrence also stores a `bounding_box` JSON:
```json
{
  "x_pct": 0.35,
  "y_pct": 0.82,
  "w_pct": 0.30,
  "h_pct": 0.02
}
```

Percentages of page dimensions, clamped to [0, 1]. The PDF overlay engine uses these to position replacement text exactly where the original text was.

---

## 5. Repeated Variable Grouping

Variables are grouped when AI determines they refer to the same real-world value:

| Original text | Grouped as |
|--------------|------------|
| "JUAN DELA CRUZ", "Juan Dela Cruz", "HON. JUAN DELA CRUZ", "Mayor Juan Dela Cruz" | `mayor_name` |
| "Municipality of Milagros", "Milagros", "MILAGROS, MASBATE" | `municipality_name` |

Grouping rules:
- Group by REAL-WORLD IDENTITY, not exact text match
- Honorific prefixes (HON., Mayor, Gov., Atty.) stripped for comparison
- ALL CAPS vs Title Case treated as same entity
- Middle initials normalized

---

## 6. Signature Block Detection

The AI prompt explicitly mandates detection of:
- Names above titles ("Municipal Mayor", "City Mayor", "Governor")
- ALL CAPS names in approval/certification blocks
- Names after "Approved by:", "Certified by:", "Noted by:", "Prepared by:"
- Names preceded by "HON.", "Honorable"
- Any name in a signature/approval area

For these occurrences:
- `source_area` = "signature_block"
- `semantic_context` = "signature_block"
- `entity_role` = "mayor_signatory"
- `recommended_replacement_strategy` = "replace_value_preserve_prefix"

---

## 7. Mayor/Signatory Detection

The AI receives explicit rules:
- `example_value` = CLEAN NAME ONLY without honorifics ("Olga T. Kho")
- `prefix_text` = "HON." or "Mayor" etc. (stored separately per occurrence)
- `casing_pattern` = "uppercase" if original was all caps

During generation, `applyCasingFromOccurrence()` + `applyPrefixSuffix()` reconstruct the correct rendering per occurrence:

```
User input: "Maria Santos"
         → applyCasing('uppercase'): "MARIA SANTOS"  
         → applyPrefixSuffix(prefix='HON.'): "HON. MARIA SANTOS"
```

---

## 8. PDF Generation Style Rules

In `DocumentGenerationService::generateFromPdfOverlay()`:

1. Load resolved value via `GenerationValueResolverService`
2. For each occurrence position (from `resolveOverlayPositions()`):
   - Apply casing from `casing_pattern` in occurrence
   - Apply prefix/suffix from `prefix_text`/`suffix_text` in occurrence
   - Set FPDF font: Helvetica (fallback — font embedding requires .php font files)
   - Apply font_weight (bold)
   - Erase original text with white rect (capped to text height × 1.4)
   - Set text color from `font_color`
   - Set alignment from `text_align` (L/C/R)
   - If text too wide: shrink font size to fit
   - Render replacement text

**Known limitation:** FPDF cannot embed arbitrary fonts without pre-generated .php font files. Font family is stored but Helvetica is used as fallback. Font size, weight, color, and alignment ARE preserved.

---

## 9. DOCX Run-Preserving Replacement

DOCX generation uses `PhpOffice\PhpWord\TemplateProcessor` which:
- Replaces `${variable_name}` placeholders in the DOCX XML
- Preserves surrounding run formatting (bold, italic, color, font size)
- Works across paragraph boundaries

The template copy with placeholders is created during `createTemplateCopy()` at analysis time.

---

## 10. Preflight Rendering Checks

Before generation (planned — see `TemplatePreflightService`):
- Missing style_snapshot → warn "Some font details missing"
- Missing bounding box → warn "Placement coordinates missing"
- Missing font info → warn "Font fallback will be used"
- Replacement too long for bbox → warn "Name may be too long for signature line"
- Occurrence in signature block → verify center alignment preserved

---

## 11. Manual "Add Missing Field" Flow

If AI misses a variable, the user can add it manually from:
- Template Editor → Add Field button
- (Planned) PDF Preview → select text → "Add as variable"

---

## 12. Testing Checklist

- [ ] Casing detection: uppercase text → `uppercase` pattern
- [ ] Casing detection: title case text → `titlecase` pattern
- [ ] Generation: uppercase original → replacement rendered uppercase
- [ ] Generation: prefix "HON." preserved in output
- [ ] Generation: prefix "Mayor" preserved in output
- [ ] Generation: no prefix → clean value output
- [ ] TemplateVariable: fixed mode hides field from form
- [ ] TemplateVariable: default mode pre-fills field
- [ ] GenerationValueResolverService: fixed beats empty input
- [ ] GenerationValueResolverService: user input beats default
- [ ] GenerationValueResolverService: one-time override beats fixed
- [ ] Sensitive detection: email type flagged as sensitive
- [ ] Sensitive detection: "name" label flagged as sensitive
- [ ] Needs review: variables with confidence < 0.7 flagged
- [ ] Source area: elements in top 10% of page → "header"
- [ ] Source area: elements in bottom 10% of page → "footer"
- [ ] Grouping: same person in 4 formats → one canonical variable

---

## 13. Known Limitations

1. **Font embedding**: FPDF requires pre-compiled .php font files to embed non-standard fonts. Only Helvetica, Times, and Courier are built-in. Font family is stored in `style_snapshot` for future use.
2. **Italic/underline**: pdftohtml XML does not expose italic or underline information.
3. **Table cell detection**: pdftohtml does not mark table cell boundaries in its XML output.
4. **Baseline**: Not available from pdftohtml.
5. **Pixel-level visual regression**: Requires headless browser — not yet implemented.
6. **Header/footer detection**: Uses y_pct heuristic (top/bottom 10%). Some documents may have content in these regions that isn't actually a header/footer.

---

## 14. Debugging Guide

To diagnose missed variables:
1. Check `variable_occurrences` table for the template — are occurrences being created?
2. Check `style_snapshot` — is `font_size`, `font_color`, `text_align` populated?
3. Check `source_area` — is header/footer/signature_block detected correctly?
4. Check Laravel logs for `VariableDetection: unexpected AI response` errors
5. Increase `maxTokens` in `analyzeWithPdf()` if AI response is truncated (current: 6000)
6. Check `needs_review = true` variables — AI flagged these as uncertain

For generation debugging:
1. Enable APP_DEBUG=true on a test environment
2. Check that `bounding_box` has valid `x_pct`, `y_pct`, `w_pct`, `h_pct` values
3. Check `casing_pattern` is set correctly on occurrences
4. Check `prefix_text` is stored per occurrence (not just on the parent variable)
