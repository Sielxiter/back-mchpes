<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidatureResult extends Model
{
    protected $fillable = [
        'candidature_id',
        'audition_score',
        'final_score',
        'pv_text',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'audition_score' => 'decimal:2',
        'final_score' => 'decimal:2',
        'validated_at' => 'datetime',
    ];
}
