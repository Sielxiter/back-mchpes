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

    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
    ];

    public const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    protected $fillable = [
        'candidature_id',
        'activite_id',
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
