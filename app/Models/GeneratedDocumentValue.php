<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-field value traceability for a generated document.
 * Records what value was actually used, where it came from, and what mode was active.
 * These records are immutable — past generations never change even if template settings change.
 */
class GeneratedDocumentValue extends Model
{
    // Source constants — what provided the final value
    const SOURCE_USER_INPUT      = 'user_input';
    const SOURCE_FIXED           = 'fixed_value';
    const SOURCE_DEFAULT         = 'default_value';
    const SOURCE_ONE_TIME        = 'one_time_override';
    const SOURCE_PORTAL          = 'portal_submission';
    const SOURCE_BULK            = 'bulk_row';
    const SOURCE_SYSTEM          = 'system_generated';

    const SOURCE_LABELS = [
        self::SOURCE_USER_INPUT => 'Typed by you',
        self::SOURCE_FIXED      => 'Fixed value',
        self::SOURCE_DEFAULT    => 'Default value',
        self::SOURCE_ONE_TIME   => 'One-time override',
        self::SOURCE_PORTAL     => 'Portal submission',
        self::SOURCE_BULK       => 'Bulk row',
        self::SOURCE_SYSTEM     => 'Auto-generated',
    ];

    protected $fillable = [
        'generated_document_id', 'template_variable_id', 'template_id', 'workspace_id',
        'final_value_used', 'submitted_value', 'value_source',
        'value_mode_at_generation', 'was_fixed_at_generation', 'was_default_at_generation',
    ];

    protected $casts = [
        'was_fixed_at_generation'   => 'boolean',
        'was_default_at_generation' => 'boolean',
    ];

    public function generatedDocument(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class);
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(TemplateVariable::class, 'template_variable_id');
    }

    /** Human-readable label for the value source. */
    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->value_source] ?? 'Unknown';
    }
}
