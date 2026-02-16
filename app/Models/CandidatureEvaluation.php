<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatureEvaluation extends Model
{
    protected $fillable = [
        'candidature_id',
        'user_id',
        'criterion',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
