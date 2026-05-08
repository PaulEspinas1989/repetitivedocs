<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class UploadedDocument extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'original_name', 'stored_name',
        'disk', 'path', 'mime_type', 'size_bytes', 'file_hash',
        'template_name', 'document_type', 'status', 'extracted_text',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSizeMbAttribute(): float
    {
        return round($this->size_bytes / 1048576, 2);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isDocx(): bool
    {
        return in_array($this->mime_type, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ]);
    }

    public function template(): HasOne
    {
        return $this->hasOne(Template::class);
    }

    public function deleteFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
