<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVariable extends Model
{
    protected $fillable = [
        'template_id', 'workspace_id', 'name', 'label', 'type',
        'description', 'example_value', 'default_value', 'options',
        'is_required', 'sort_order', 'approval_status', 'ai_suggested',
        'text_positions',
    ];

    protected $casts = [
        'options'        => 'array',
        'is_required'    => 'boolean',
        'ai_suggested'   => 'boolean',
        'sort_order'     => 'integer',
        'text_positions' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
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
