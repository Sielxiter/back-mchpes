<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CandidatureActivite extends Model
{
    protected $fillable = [
        'candidature_id',
        'type',
        'category',
        'subcategory',
        'count',
    ];

    protected $casts = [
        'count' => 'integer',
    ];

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    /**
     * An activity can have multiple justificatif documents.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CandidatureDocument::class, 'activite_id');
    }

    public function hasRequiredDocuments(): bool
    {
        return $this->count === 0 || $this->documents()->exists();
    }
}
