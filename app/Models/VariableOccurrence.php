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
        if (empty($this->bounding_box)) {
            return null;
        }

        return array_merge($this->bounding_box, [
            'page'       => $this->page_number,
            'font_size'  => $this->style_snapshot['font_size']  ?? 10,
            'font_color' => $this->style_snapshot['font_color'] ?? '#000000',
            'text_align' => $this->style_snapshot['text_align'] ?? 'L',
        ]);
    }
}
