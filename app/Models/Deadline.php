<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deadline extends Model
{
    protected $fillable = [
        'stage',
        'due_at',
        'reminder_enabled',
        'created_by',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'reminder_enabled' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->due_at->isPast();
    }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, now()->diffInDays($this->due_at, false));
    }
}
