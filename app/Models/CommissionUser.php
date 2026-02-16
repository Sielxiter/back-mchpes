<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionUser extends Model
{
    protected $table = 'commission_users';

    protected $fillable = [
        'commission_id',
        'user_id',
        'is_president',
    ];

    protected $casts = [
        'is_president' => 'boolean',
    ];

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
