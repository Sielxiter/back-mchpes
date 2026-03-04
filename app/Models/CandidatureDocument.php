<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CandidatureDocument extends Model
{
    public const TYPE_PROFILE_PDF = 'profile_pdf';
    public const TYPE_ENSEIGNEMENTS_PDF = 'enseignements_pdf';
    public const TYPE_PFE_PDF = 'pfe_pdf';
    public const TYPE_ACTIVITE_ATTESTATION = 'activite_attestation';
    public const TYPE_SIGNED_DOCUMENT = 'signed_document';

    // Generated attestation PDFs (server-side)
    public const TYPE_ATTESTATION_ENS_PDF = 'attestation_ens_pdf';
    public const TYPE_ATTESTATION_RECH_PDF = 'attestation_rech_pdf';

    // Per-type signed re-upload variants
    public const TYPE_SIGNED_PROFILE = 'signed_profile';
    public const TYPE_SIGNED_ENSEIGNEMENTS = 'signed_enseignements';
    public const TYPE_SIGNED_PFE = 'signed_pfe';
    public const TYPE_SIGNED_ATTESTATION_ENS = 'signed_attestation_ens';
    public const TYPE_SIGNED_ATTESTATION_RECH = 'signed_attestation_rech';

    /**
     * All generated PDF types (server-side generation)
     */
    public const GENERATED_TYPES = [
        self::TYPE_PROFILE_PDF,
        self::TYPE_ENSEIGNEMENTS_PDF,
        self::TYPE_PFE_PDF,
        self::TYPE_ATTESTATION_ENS_PDF,
        self::TYPE_ATTESTATION_RECH_PDF,
    ];

    /**
     * Mapping from generated type to its signed counterpart
     */
    public const SIGNED_TYPE_MAP = [
        self::TYPE_PROFILE_PDF => self::TYPE_SIGNED_PROFILE,
        self::TYPE_ENSEIGNEMENTS_PDF => self::TYPE_SIGNED_ENSEIGNEMENTS,
        self::TYPE_PFE_PDF => self::TYPE_SIGNED_PFE,
        self::TYPE_ATTESTATION_ENS_PDF => self::TYPE_SIGNED_ATTESTATION_ENS,
        self::TYPE_ATTESTATION_RECH_PDF => self::TYPE_SIGNED_ATTESTATION_RECH,
    ];

    /**
     * All signed upload types
     */
    public const SIGNED_TYPES = [
        self::TYPE_SIGNED_PROFILE,
        self::TYPE_SIGNED_ENSEIGNEMENTS,
        self::TYPE_SIGNED_PFE,
        self::TYPE_SIGNED_ATTESTATION_ENS,
        self::TYPE_SIGNED_ATTESTATION_RECH,
    ];

    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
    ];

    public const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    protected $fillable = [
        'candidature_id',
        'activite_id',
        'generated_document_id',
        'type',
        'original_name',
        'stored_name',
        'mime_type',
        'size',
        'path',
        'hash',
        'is_verified',
        'scanned_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_verified' => 'boolean',
        'scanned_at' => 'datetime',
    ];

    protected $hidden = [
        'path',
        'stored_name',
    ];

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(CandidatureActivite::class, 'activite_id');
    }

    /**
     * The generated document this signed upload relates to.
     */
    public function generatedDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'generated_document_id');
    }

    /**
     * The signed version(s) uploaded for this generated document.
     */
    public function signedVersions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'generated_document_id');
    }

    /**
     * Check if this document type is a generated PDF type.
     */
    public function isGenerated(): bool
    {
        return in_array($this->type, self::GENERATED_TYPES);
    }

    /**
     * Check if this document type is a signed upload type.
     */
    public function isSigned(): bool
    {
        return in_array($this->type, self::SIGNED_TYPES);
    }

    public function getUrlAttribute(): string
    {
        return route('candidature.document.download', $this->id);
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk('private')->path($this->path);
    }

    public function verifyIntegrity(): bool
    {
        if (!$this->hash) {
            return true;
        }

        $currentHash = hash_file('sha256', $this->full_path);
        return hash_equals($this->hash, $currentHash);
    }

    protected static function booted(): void
    {
        static::deleting(function (CandidatureDocument $document) {
            Storage::disk('private')->delete($document->path);
        });
    }
}
