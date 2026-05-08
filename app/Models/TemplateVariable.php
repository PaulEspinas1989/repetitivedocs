<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateVariable extends Model
{
    // value_mode constants — use these instead of magic strings
    const MODE_ASK        = 'ask_each_time';
    const MODE_DEFAULT    = 'default_editable';
    const MODE_FIXED      = 'fixed_hidden';

    // User-facing labels for each mode
    const MODE_LABELS = [
        self::MODE_ASK     => 'Ask every time',
        self::MODE_DEFAULT => 'Use as default',
        self::MODE_FIXED   => 'Keep as fixed',
    ];

    // Field types considered potentially sensitive (require user confirmation to fix)
    const SENSITIVE_TYPES = ['email', 'phone'];
    const SENSITIVE_LABELS = ['recipient name', 'name', 'email', 'phone', 'address',
                               'id', 'birthdate', 'bank', 'salary', 'password', 'tax'];

    protected $fillable = [
        'template_id', 'workspace_id', 'name', 'label', 'type',
        'description', 'example_value', 'default_value', 'options',
        'is_required', 'sort_order', 'approval_status', 'ai_suggested',
        'text_positions', 'occurrences',
        'canonical_variable_id', 'semantic_type', 'entity_role',
        'grouping_confidence', 'grouping_reason',
        // Value-mode fields
        'value_mode', 'fixed_value',
        'fixed_value_set_by_generation_id', 'fixed_value_set_by_user_id', 'fixed_value_set_at',
        'show_when_fixed', 'ai_suggested_mode', 'ai_suggested_mode_reason',
        'user_confirmed_mode', 'is_sensitive_flag',
        // Review flags
        'needs_review', 'needs_review_reason',
        // Date formatting
        'date_format',
    ];

    protected $casts = [
        'options'             => 'array',
        'is_required'         => 'boolean',
        'ai_suggested'        => 'boolean',
        'sort_order'          => 'integer',
        'text_positions'      => 'array',
        'occurrences'         => 'integer',
        'grouping_confidence' => 'integer',
        // Value-mode casts
        'show_when_fixed'       => 'boolean',
        'user_confirmed_mode'   => 'boolean',
        'is_sensitive_flag'     => 'boolean',
        'fixed_value_set_at'    => 'datetime',
        // Review casts
        'needs_review'          => 'boolean',
    ];

    public function isRepeating(): bool
    {
        return ($this->occurrences ?: 1) > 1;
    }

    // ── Value mode helpers ────────────────────────────────────────────

    public function isFixed(): bool
    {
        return $this->value_mode === self::MODE_FIXED;
    }

    public function isDefault(): bool
    {
        return $this->value_mode === self::MODE_DEFAULT;
    }

    public function isAskEachTime(): bool
    {
        return $this->value_mode === self::MODE_ASK || empty($this->value_mode);
    }

    /** Returns true if this field requires user input in the fillable form. */
    public function requiresUserInput(): bool
    {
        return $this->value_mode !== self::MODE_FIXED;
    }

    /** Returns true if this field should be hidden from the normal generation form. */
    public function isHiddenFromForm(): bool
    {
        return $this->value_mode === self::MODE_FIXED && !$this->show_when_fixed;
    }

    /** The user-facing label for the current mode. */
    public function valueModeLabel(): string
    {
        return self::MODE_LABELS[$this->value_mode ?? self::MODE_ASK] ?? 'Ask every time';
    }

    /**
     * Check if this field label suggests it might contain personal/sensitive info.
     * AI detection: helps warn users before they fix sensitive values.
     */
    public function looksLikeSensitive(): bool
    {
        if (in_array($this->type, self::SENSITIVE_TYPES, true)) {
            return true;
        }
        $label = mb_strtolower($this->label ?? '');
        foreach (self::SENSITIVE_LABELS as $keyword) {
            if (str_contains($label, $keyword)) {
                return true;
            }
        }
        return false;
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
     *
     * Legacy text_positions rows have keys: page, x_pct, y_pct, w_pct, h_pct, font_size, font_color.
     * They may be missing text_align, font_family, font_weight — fill defaults so the
     * generation engine never sees missing keys.
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

        // Normalise legacy positions — guarantee all keys (including new rendering context)
        return collect($this->text_positions ?? [])
            ->map(fn($pos) => [
                'page'             => (int)    ($pos['page']             ?? 1),
                'x_pct'           => (float)  ($pos['x_pct']            ?? 0),
                'y_pct'           => (float)  ($pos['y_pct']            ?? 0),
                'w_pct'           => (float)  ($pos['w_pct']            ?? 0),
                'h_pct'           => (float)  ($pos['h_pct']            ?? 0),
                'font_size'        => (float)  ($pos['font_size']        ?? 10),
                'font_color'       => (string) ($pos['font_color']       ?? '#000000'),
                'font_family'      => (string) ($pos['font_family']      ?? ''),
                'font_weight'      => (string) ($pos['font_weight']      ?? 'normal'),
                'text_align'       => (string) ($pos['text_align']       ?? 'L'),
                // Rendering context — may not be in legacy records; safe defaults applied
                'prefix_text'      => (string) ($pos['prefix_text']      ?? ''),
                'suffix_text'      => (string) ($pos['suffix_text']      ?? ''),
                'original_text'    => (string) ($pos['original_text']    ?? ''),
                'casing_pattern'   => (string) ($pos['casing_pattern']   ?? 'mixed'),
                'source_area'      => (string) ($pos['source_area']      ?? 'body'),
                'semantic_context' => (string) ($pos['semantic_context'] ?? ''),
            ])
            ->all();
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
