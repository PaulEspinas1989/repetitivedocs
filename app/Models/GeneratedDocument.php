<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GeneratedDocument extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'template_id',
        'variable_values', 'file_path', 'file_name', 'disk', 'status',
    ];

    protected $casts = [
        'variable_values' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function downloadUrl(): string
    {
        return route('generated-documents.download', $this->id);
    }

    public function fileExists(): bool
    {
        return $this->file_path && Storage::disk($this->disk)->exists($this->file_path);
    }
}
