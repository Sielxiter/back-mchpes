<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\CandidatureDocument;

class Candidature extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'current_step',
        'status',
        'submitted_at',
        'locked_at',
        'rejection_reason',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CandidatureProfile::class);
    }

    public function enseignements(): HasMany
    {
        return $this->hasMany(CandidatureEnseignement::class);
    }

    public function pfes(): HasMany
    {
        return $this->hasMany(CandidaturePfe::class);
    }

    public function activites(): HasMany
    {
        return $this->hasMany(CandidatureActivite::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidatureDocument::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->status === self::STATUS_SUBMITTED;
    }

    public function canBeEdited(): bool
    {
        return !$this->isLocked() && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_BLOCKED]);
    }

    public function getProgressAttribute(): array
    {
        $hasProfilePdf = $this->documents()
            ->where('type', CandidatureDocument::TYPE_PROFILE_PDF)
            ->exists();

        $profileComplete = ($this->profile?->is_complete ?? false) && $hasProfilePdf;

        $enseignementsComplete = $this->enseignements()->exists();

        $hasPfeEntries = $this->pfes()->exists();
        $hasPfePdf = $this->documents()
            ->where('type', CandidatureDocument::TYPE_PFE_PDF)
            ->exists();
        $pfeComplete = $hasPfeEntries && $hasPfePdf;

        $hasActivitesEnseignement = $this->activites()->where('type', 'enseignement')->exists();
        $missingDocsEnseignement = $this->activites()
            ->where('type', 'enseignement')
            ->where('count', '>', 0)
            ->whereDoesntHave('documents')
            ->exists();
        $activitesEnseignementComplete = $hasActivitesEnseignement && !$missingDocsEnseignement;

        $hasActivitesRecherche = $this->activites()->where('type', 'recherche')->exists();
        $missingDocsRecherche = $this->activites()
            ->where('type', 'recherche')
            ->where('count', '>', 0)
            ->whereDoesntHave('documents')
            ->exists();
        $activitesRechercheComplete = $hasActivitesRecherche && !$missingDocsRecherche;

        $steps = [
            1 => $profileComplete,
            2 => $enseignementsComplete,
            3 => $pfeComplete,
            4 => $activitesEnseignementComplete,
            5 => $activitesRechercheComplete,
        ];

        $completed = collect($steps)->filter()->count();
        $total = count($steps);

        return [
            'steps' => $steps,
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }
}
