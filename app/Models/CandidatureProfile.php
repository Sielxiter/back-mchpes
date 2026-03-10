<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CandidatureProfile extends Model
{
    protected $fillable = [
        'candidature_id',
        'nom',
        'prenom',
        'nom_ar',
        'prenom_ar',
        'cin',
        'email',
        'date_naissance',
        'etablissement',
        'ville',
        'departement',
        'grade_actuel',
        'date_recrutement_es',
        'date_recrutement_fp',
        'numero_som',
        'telephone',
        'specialite',
        'date_soutenance_habilitation',
        'exactitude_info',
        'acceptation_termes',
        'is_complete',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_recrutement_es' => 'date',
        'date_recrutement_fp' => 'date',
        'date_soutenance_habilitation' => 'date',
        'exactitude_info' => 'boolean',
        'acceptation_termes' => 'boolean',
        'is_complete' => 'boolean',
    ];

    protected $appends = ['anciennete'];

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    public function getAncienneteAttribute(): ?array
    {
        if (!$this->date_recrutement_es) {
            return null;
        }

        $now = Carbon::now();
        $recrutement = Carbon::parse($this->date_recrutement_es);
        
        $years = $recrutement->diffInYears($now);
        $months = $recrutement->copy()->addYears($years)->diffInMonths($now);

        return [
            'years' => $years,
            'months' => $months,
            'total_months' => $recrutement->diffInMonths($now),
        ];
    }

    public function checkCompleteness(): bool
    {
        $required = [
            'nom', 'prenom', 'nom_ar', 'prenom_ar', 'cin', 'email', 'date_naissance', 'etablissement',
            'ville', 'departement', 'grade_actuel', 'date_recrutement_es',
            'telephone', 'specialite'
        ];

        foreach ($required as $field) {
            if (empty($this->$field)) {
                return false;
            }
        }

        return $this->exactitude_info && $this->acceptation_termes;
    }
}
