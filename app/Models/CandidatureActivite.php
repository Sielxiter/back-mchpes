<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function document(): HasOne
    {
        return $this->hasOne(CandidatureDocument::class, 'activite_id');
    }

    public function hasRequiredDocument(): bool
    {
        return $this->count === 0 || $this->document !== null;
    }
}
