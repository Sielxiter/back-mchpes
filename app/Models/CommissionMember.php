<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionMember extends Model
{
    protected $fillable = [
        'commission_id',
        'nom',
        'prenom',
        'etablissement',
        'universite',
        'grade',
        'specialite',
        'email',
        'telephone',
        'is_president',
    ];

    protected $casts = [
        'is_president' => 'boolean',
    ];

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }
}
