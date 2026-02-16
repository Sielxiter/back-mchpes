<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidaturePfe extends Model
{
    protected $table = 'candidature_pfes';

    protected $fillable = [
        'candidature_id',
        'annee_universitaire',
        'intitule',
        'niveau',
        'volume_horaire',
    ];

    protected $casts = [
        'volume_horaire' => 'integer',
    ];

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }
}
