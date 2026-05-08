<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'price_monthly',
        'template_limit', 'document_limit', 'ai_credit_limit',
        'file_size_limit_mb', 'bulk_generation_limit', 'max_users',
        'storage_days', 'features', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'features'      => 'array',
        'is_active'     => 'boolean',
        'price_monthly' => 'decimal:2',
    ];

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public static function free(): self
    {
        return static::where('slug', 'free')->firstOrFail();
    }
}
