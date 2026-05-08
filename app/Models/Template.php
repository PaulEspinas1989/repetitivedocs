<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'uploaded_document_id',
        'name', 'document_type', 'status', 'readiness_score',
        'template_docx_path',
    ];

    protected $casts = [
        'readiness_score' => 'integer',
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

    public function variableSummary(): array
    {
        $vars = $this->variables;

        $categories = [
            'people'        => $vars->whereIn('type', ['text'])->filter(fn($v) => str_contains(strtolower($v->label), 'name') || str_contains(strtolower($v->description ?? ''), 'name'))->count(),
            'dates'         => $vars->where('type', 'date')->count(),
            'amounts'       => $vars->whereIn('type', ['currency', 'number'])->count(),
            'locations'     => $vars->where('type', 'address')->count(),
            'contacts'      => $vars->whereIn('type', ['email', 'phone'])->count(),
            'organizations' => $vars->filter(fn($v) => str_contains(strtolower($v->label), 'company') || str_contains(strtolower($v->label), 'organization') || str_contains(strtolower($v->label), 'employer'))->count(),
        ];

        return [
            'total'      => $vars->count(),
            'categories' => $categories,
            'pending'    => $vars->where('approval_status', 'pending')->count(),
            'approved'   => $vars->where('approval_status', 'approved')->count(),
            'rejected'   => $vars->where('approval_status', 'rejected')->count(),
        ];
    }
}
