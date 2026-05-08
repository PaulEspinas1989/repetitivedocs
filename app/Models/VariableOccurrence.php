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
        if (empty($bbox) || !isset($bbox['x_pct'])) {
            return null;
        }

        // Cast guarantees null → [] but be explicit to survive future cast changes
        $style = $this->style_snapshot ?: [];

        return [
            'page'        => $this->page_number ?? 1,
            'x_pct'      => (float) ($bbox['x_pct'] ?? 0),
            'y_pct'      => (float) ($bbox['y_pct'] ?? 0),
            'w_pct'      => (float) ($bbox['w_pct'] ?? 0),
            'h_pct'      => (float) ($bbox['h_pct'] ?? 0),
            'font_size'   => (float) ($style['font_size']  ?? 10),
            'font_color'  => (string) ($style['font_color'] ?? '#000000'),
            'font_family' => (string) ($style['font_family'] ?? ''),
            'font_weight' => (string) ($style['font_weight'] ?? 'normal'),
            'text_align'  => (string) ($style['text_align']  ?? 'L'),
        ];
    }
}
