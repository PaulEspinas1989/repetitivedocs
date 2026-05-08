<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateVariable extends Model
{
    protected $fillable = [
        'template_id', 'workspace_id', 'name', 'label', 'type',
        'description', 'example_value', 'default_value', 'options',
        'is_required', 'sort_order', 'approval_status', 'ai_suggested',
        'text_positions', 'occurrences',
        'canonical_variable_id', 'semantic_type', 'entity_role',
        'grouping_confidence', 'grouping_reason',
    ];

    protected $casts = [
        'options'             => 'array',
        'is_required'         => 'boolean',
        'ai_suggested'        => 'boolean',
        'sort_order'          => 'integer',
        'text_positions'      => 'array',
        'occurrences'         => 'integer',
        'grouping_confidence' => 'integer',
    ];

    public function isRepeating(): bool
    {
        return ($this->occurrences ?: 1) > 1;
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** All stored occurrence records for this variable. */
    public function occurrenceRecords(): HasMany
    {
        return $this->hasMany(VariableOccurrence::class, 'template_variable_id');
    }

    /** Active (non-ignored, non-unlinked) occurrences for generation. */
    public function activeOccurrences(): HasMany
    {
        return $this->hasMany(VariableOccurrence::class, 'template_variable_id')
                    ->where('status', 'active');
    }

    /**
     * Return all overlay positions — prefers VariableOccurrence records (richer data),
     * falls back to the legacy text_positions JSON array.
     */
    public function resolveOverlayPositions(): array
    {
        if ($this->relationLoaded('activeOccurrences') && $this->activeOccurrences->isNotEmpty()) {
            return $this->activeOccurrences
                ->map(fn($occ) => $occ->toOverlayPosition())
                ->filter()
                ->values()
                ->all();
        }

        return $this->text_positions ?? [];
    }

    public function typeBadgeColor(): string
    {
        return match ($this->type) {
            'date'     => 'bg-success/10 text-success',
            'currency' => 'bg-warning/10 text-warning',
            'number'   => 'bg-warning/10 text-warning',
            'email'    => 'bg-primary/10 text-primary',
            'phone'    => 'bg-primary/10 text-primary',
            'address'  => 'bg-slate/10 text-slate',
            'select'   => 'bg-purple-100 text-purple-600',
            default    => 'bg-blue-soft text-primary',
        };
    }
}
