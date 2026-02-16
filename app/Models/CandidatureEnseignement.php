<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatureEnseignement extends Model
{
    public const CONVERSION_FACTORS = [
        'CM' => 1.5,
        'TD' => 1.25,
        'TP' => 1.0,
    ];

    protected $fillable = [
        'candidature_id',
        'annee_universitaire',
        'intitule',
        'type_enseignement',
        'type_module',
        'niveau',
        'volume_horaire',
        'equivalent_tp',
    ];

    protected $casts = [
        'volume_horaire' => 'integer',
        'equivalent_tp' => 'decimal:2',
    ];

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    public static function calculateEquivalentTp(string $type, int $volumeHoraire): float
    {
        $factor = self::CONVERSION_FACTORS[$type] ?? 1.0;
        return round($volumeHoraire * $factor, 2);
    }

    protected static function booted(): void
    {
        static::creating(function (CandidatureEnseignement $enseignement) {
            if (empty($enseignement->equivalent_tp)) {
                $enseignement->equivalent_tp = self::calculateEquivalentTp(
                    $enseignement->type_enseignement,
                    $enseignement->volume_horaire
                );
            }
        });
    }
}
