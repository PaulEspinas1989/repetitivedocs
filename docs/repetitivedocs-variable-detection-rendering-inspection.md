# RepetitiveDocs — Variable Detection & Rendering: Inspection Notes

*Generated during implementation sprint. Last updated: 2026-05-08.*

---

## Current Pipeline Summary

```
Upload → pdftohtml (XML) → extractPdfTextElements()
       → AI Claude (PDF or DOCX text) → buildPrompt()
       → parseResponse() / createOccurrenceRecords()
       → TemplateVariable + VariableOccurrence rows
       → Fillable Form → GenerationValueResolverService → DocumentGenerationService
       → generateFromPdfOverlay() / generateFromDocx()
```

---

## What extractPdfTextElements() Currently Captures

✅ page, top, left, width, height (raw coords)
✅ page_width, page_height
✅ font_size (from fontspec)
✅ font_color (from fontspec hex)
✅ font_family (from fontspec, e.g. "TimesNewRoman,Bold")
✅ font_weight (derived: if "bold" in family name)
✅ text (trimmed)

❌ italic (not in pdftohtml XML)
❌ underline (not in pdftohtml XML)
❌ source_area (header/footer/body/signature) — ADDED in migration 000019
❌ casing_pattern of original text — ADDED in migration 000019
❌ table cell context (not available from pdftohtml)
❌ baseline (not in pdftohtml XML)

---

## What findAllTextPositions() Does

- Groups elements into lines (within 5px vertical tolerance)
- Detects exact match OR contained match (≥60% of line)
- Calculates alignment: center if 38-62% of page width, right if ≥85%, left otherwise
- Returns ALL matches (no break per page)
- Clamps percentages to [0,1]
- Returns: page, x_pct, y_pct, w_pct, h_pct, font_size, font_color, font_family, font_weight, text_align

---

## Critical Generation Gaps Found

### GAP 1: Prefix/suffix NOT re-applied (HIGHEST IMPACT)
- prefix_text and suffix_text ARE stored in VariableOccurrence
- But generateFromPdfOverlay() never uses them
- "HON. JUAN DELA CRUZ" → replaced with "Maria Santos" (should be "HON. MARIA SANTOS")
- **Fixed in migration 000019 + DocumentGenerationService update**

### GAP 2: Casing NOT preserved
- If original was "JUAN DELA CRUZ" (all-caps), new value renders as-is (whatever user typed)
- No logic to detect or apply casing pattern per occurrence
- **Fixed: detectCasingPattern() + applyCasing() added to DocumentGenerationService**

### GAP 3: Font family stored but IGNORED
- font_family is extracted and stored in style_snapshot
- But generation always uses Helvetica
- FPDF cannot embed arbitrary fonts without .php font files
- **Partial fix: apply bold/weight correctly; font-family noted as limitation**

### GAP 4: No source_area tracking
- Can't distinguish body vs header/footer vs signature block
- Header/footer may have repeating content that needs special handling
- **Fixed: source_area added to variable_occurrences, detection from y_pct**

### GAP 5: No casing_pattern column
- Was computed fresh each time, never stored
- **Fixed: stored per occurrence after extraction**

### GAP 6: AI prompt doesn't request source_area/nearby_label per occurrence
- AI can see document layout but doesn't return structured context
- **Fixed: updated buildPrompt() to request these fields**

### GAP 7: Needs review candidates not surfaced to user
- AI "uncertain" candidates were not stored or shown
- **Fixed: needs_review column added to template_variables**

---

## Modules Impacted by Changes

| Module | Change |
|--------|--------|
| `VariableDetectionService` | source_area detection, improved prompt, store casing_pattern |
| `DocumentGenerationService` | prefix/suffix application, casing transformation, private helpers |
| `VariableOccurrence` model | new fillable fields, toOverlayPosition() returns prefix/suffix/casing |
| `TemplateVariable` model | needs_review field |
| `automation-map.blade.php` | Needs Review section, coverage score |
| Migrations 000019 | New columns on both tables |

---

## Implementation Path

1. Migration 000019 — add source_area, casing_pattern, nearby_label, detection_source to variable_occurrences; add needs_review to template_variables
2. Update models
3. Improve extractPdfTextElements() to detect source_area from y_pct
4. Improve buildPrompt() with structured occurrence output including source_area, nearby_label, casing_pattern
5. Update createOccurrenceRecords() to store new fields
6. Fix generateFromPdfOverlay() to apply prefix/suffix and casing per occurrence
7. Surface needs_review variables in automation-map UI
8. Tests + documentation

---

## Known Limitations (Not Fixable in This Sprint)

- FPDF cannot embed arbitrary fonts without .php font files — font-family is stored but Helvetica fallback is used
- pdftohtml does not provide table cell boundaries in standard XML output
- pdftohtml does not provide italic/underline in XML output
- Baseline extraction is not available from pdftohtml
- Pixel-perfect visual regression testing requires a headless browser — not implemented yet
