<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariableOccurrence extends Model
{
    protected $fillable = [
        'template_variable_id', 'template_id', 'workspace_id',
        'page_number', 'original_text', 'normalized_text',
        'prefix_text', 'suffix_text', 'context_before', 'context_after',
        'section_label', 'semantic_context', 'bounding_box', 'style_snapshot',
        'replacement_strategy', 'confidence_pct', 'status', 'ai_reason',
        // New detection metadata fields
        'source_area', 'nearby_label', 'casing_pattern', 'detection_source',
    ];

    protected $casts = [
        'bounding_box'  => 'array',
        'style_snapshot' => 'array',
        'page_number'   => 'integer',
        'confidence_pct' => 'integer',
    ];

    public function variable(): BelongsTo
    {
        return $this->belongsTo(TemplateVariable::class, 'template_variable_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** Human-readable confidence label (no raw decimals shown to users). */
    public function confidenceLabel(): string
    {
        return match (true) {
            $this->confidence_pct >= 85 => 'High',
            $this->confidence_pct >= 65 => 'Needs review',
            default                     => 'Suggested',
        };
    }

    /** Return position array for the PDF overlay engine. */
    public function toOverlayPosition(): ?array
    {
        $bbox = $this->bounding_box;
        if (empty($bbox) || (!isset($bbox['x_pct']) && !isset($bbox['x0']))) {
            return null;
        }

        $style = $this->style_snapshot ?: [];

        return [
            'page'             => $this->page_number ?? 1,
            // PDF-point coords (from pdfplumber) — used by Python /generate
            'x0'              => (float)  ($bbox['x0']    ?? 0),
            'y0'              => (float)  ($bbox['y0']    ?? 0),
            'x1'              => (float)  ($bbox['x1']    ?? 0),
            'y1'              => (float)  ($bbox['y1']    ?? 0),
            // Percentage coords — kept for display/legacy
            'x_pct'           => (float)  ($bbox['x_pct'] ?? 0),
            'y_pct'           => (float)  ($bbox['y_pct'] ?? 0),
            'w_pct'           => (float)  ($bbox['w_pct'] ?? 0),
            'h_pct'           => (float)  ($bbox['h_pct'] ?? 0),
            'font_size'        => (float)  ($style['font_size']   ?? 10),
            'font_color'       => (string) ($style['font_color']  ?? '#000000'),
            'font_family'      => (string) ($style['font_family'] ?? ''),
            'font_weight'      => (string) ($style['font_weight'] ?? 'normal'),
            'text_align'       => (string) ($style['text_align']  ?? 'L'),
            'prefix_text'      => (string) ($this->prefix_text      ?? ''),
            'suffix_text'      => (string) ($this->suffix_text      ?? ''),
            'original_text'    => (string) ($this->original_text    ?? ''),
            'casing_pattern'   => (string) ($this->casing_pattern   ?? 'mixed'),
            'source_area'      => (string) ($this->source_area      ?? 'body'),
            'semantic_context' => (string) ($this->semantic_context ?? ''),
        ];
    }
}
