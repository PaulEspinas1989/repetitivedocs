<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    // save_mode constants
    const SAVE_DRAFT    = 'draft';
    const SAVE_ONE_TIME = 'one_time';
    const SAVE_TEMPLATE = 'saved_template';

    protected $fillable = [
        'workspace_id', 'user_id', 'uploaded_document_id',
        'name', 'document_type', 'status', 'readiness_score',
        'template_docx_path',
        // Save-mode fields (new)
        'save_mode', 'is_saved_template', 'saved_at', 'saved_by_user_id',
        'fixed_fields_reviewed', 'fixed_fields_reviewed_at',
    ];

    protected $casts = [
        'readiness_score'          => 'integer',
        'is_saved_template'        => 'boolean',
        'fixed_fields_reviewed'    => 'boolean',
        'saved_at'                 => 'datetime',
        'fixed_fields_reviewed_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadedDocument(): BelongsTo
    {
        return $this->belongsTo(UploadedDocument::class);
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class)->latest();
    }

    public function variables(): HasMany
    {
        return $this->hasMany(TemplateVariable::class)->orderBy('sort_order');
    }

    public function approvedVariables(): HasMany
    {
        return $this->hasMany(TemplateVariable::class)
                    ->where('approval_status', 'approved')
                    ->orderBy('sort_order');
    }

    public function pendingVariables(): HasMany
    {
        return $this->hasMany(TemplateVariable::class)
                    ->where('approval_status', 'pending')
                    ->orderBy('sort_order');
    }

    /** Variables the user must fill every generation. */
    public function formVariables(): HasMany
    {
        return $this->hasMany(TemplateVariable::class)
                    ->where('approval_status', 'approved')
                    ->whereIn('value_mode', [TemplateVariable::MODE_ASK, TemplateVariable::MODE_DEFAULT])
                    ->orderBy('sort_order');
    }

    /** Variables that are hidden from the form and auto-filled. */
    public function fixedVariables(): HasMany
    {
        return $this->hasMany(TemplateVariable::class)
                    ->where('approval_status', 'approved')
                    ->where('value_mode', TemplateVariable::MODE_FIXED)
                    ->orderBy('sort_order');
    }

    // ── Save-mode helpers ─────────────────────────────────────────────

    public function isSavedTemplate(): bool
    {
        return (bool) $this->is_saved_template;
    }

    public function isOneTime(): bool
    {
        return $this->save_mode === self::SAVE_ONE_TIME;
    }

    public function needsFixedFieldsReview(): bool
    {
        return $this->is_saved_template && !$this->fixed_fields_reviewed;
    }

    public function markSaved(int $userId): void
    {
        $this->update([
            'is_saved_template' => true,
            'save_mode'         => self::SAVE_TEMPLATE,
            'saved_at'          => now(),
            'saved_by_user_id'  => $userId,
        ]);
    }

    public function markFixedFieldsReviewed(): void
    {
        $this->update([
            'fixed_fields_reviewed'    => true,
            'fixed_fields_reviewed_at' => now(),
        ]);
    }

    // ── Variable summaries ────────────────────────────────────────────

    public function variableSummary(): array
    {
        $vars = $this->variables;

        $categories = [
            'people'        => $vars->whereIn('type', ['text'])
                ->filter(fn($v) => str_contains(strtolower($v->label), 'name')
                    || str_contains(strtolower($v->description ?? ''), 'name'))
                ->count(),
            'dates'         => $vars->where('type', 'date')->count(),
            'amounts'       => $vars->whereIn('type', ['currency', 'number'])->count(),
            'locations'     => $vars->where('type', 'address')->count(),
            'contacts'      => $vars->whereIn('type', ['email', 'phone'])->count(),
            'organizations' => $vars->filter(fn($v) =>
                str_contains(strtolower($v->label), 'company')
                || str_contains(strtolower($v->label), 'organization')
                || str_contains(strtolower($v->label), 'employer'))
                ->count(),
        ];

        $approved = $vars->where('approval_status', 'approved');

        return [
            'total'           => $vars->count(),
            'categories'      => $categories,
            'pending'         => $vars->where('approval_status', 'pending')->count(),
            'approved'        => $approved->count(),
            'rejected'        => $vars->where('approval_status', 'rejected')->count(),
            'fixed_count'     => $approved->where('value_mode', TemplateVariable::MODE_FIXED)->count(),
            'default_count'   => $approved->where('value_mode', TemplateVariable::MODE_DEFAULT)->count(),
            'ask_count'       => $approved->whereIn('value_mode', [TemplateVariable::MODE_ASK, ''])->count(),
        ];
    }
}
