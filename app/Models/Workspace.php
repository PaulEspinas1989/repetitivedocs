<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Workspace extends Model
{
    protected $fillable = [
        'name', 'slug', 'owner_id', 'plan_id', 'ai_credits_remaining',
    ];

    protected $casts = [
        'ai_credits_remaining' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasFeature(string $feature): bool
    {
        return $this->plan?->hasFeature($feature) ?? false;
    }
}
