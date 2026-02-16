<?php

namespace App\Services;

use App\Models\CandidatureDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class SecureFileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
    ];

    private const ALLOWED_EXTENSIONS = [
        'pdf',
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    private const DANGEROUS_PATTERNS = [
        '/<\?php/i',
        '/<script/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/on\w+\s*=/i',
    ];

    /**
     * Validate and store a file securely
     */
    public function upload(
        UploadedFile $file,
        int $candidatureId,
        string $type,
        ?int $activiteId = null
    ): CandidatureDocument {
        // Validate the file
        $canonicalMimeType = $this->validateFile($file);

        // Generate secure filename
        $storedName = $this->generateSecureFilename($file);
        
        // Determine storage path
        $path = $this->getStoragePath($candidatureId, $type);
        $fullPath = $path . '/' . $storedName;

        // Store file in private disk
        Storage::disk('private')->putFileAs($path, $file, $storedName);

        // Calculate file hash for integrity verification
        $hash = hash_file('sha256', Storage::disk('private')->path($fullPath));

        // Create document record
        return CandidatureDocument::create([
            'candidature_id' => $candidatureId,
            'activite_id' => $activiteId,
            'type' => $type,
            'original_name' => $this->sanitizeFilename($file->getClientOriginalName()),
            'stored_name' => $storedName,
            'mime_type' => $canonicalMimeType,
            'size' => $file->getSize(),
            'path' => $fullPath,
            'hash' => $hash,
            'is_verified' => false,
        ]);
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file): string
    {
        // Check if file is valid
        if (!$file->isValid()) {
            throw new Exception('Le fichier téléchargé est invalide.');
        }

        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new Exception('Le fichier dépasse la taille maximale autorisée (10 Mo).');
        }

        // Validate extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new Exception('Extension de fichier non autorisée.');
        }

        // Determine canonical MIME type.
        // On some Windows/PHP setups, $file->getMimeType() may return application/octet-stream for images.
        $detectedMimeType = (string) $file->getMimeType();
        $clientMimeType = (string) $file->getClientMimeType();
        $canonicalMimeType = $detectedMimeType;

        if (!in_array($canonicalMimeType, self::ALLOWED_MIME_TYPES)) {
            if (in_array($clientMimeType, self::ALLOWED_MIME_TYPES)) {
                $canonicalMimeType = $clientMimeType;
            } elseif (in_array($detectedMimeType, ['application/octet-stream', 'binary/octet-stream', 'application/x-empty'], true)) {
                $canonicalMimeType = match ($extension) {
                    'pdf' => 'application/pdf',
                    default => $detectedMimeType,
                };
            }
        }

        if (!in_array($canonicalMimeType, self::ALLOWED_MIME_TYPES)) {
            throw new Exception('Type de fichier non autorisé. Format accepté: PDF.');
        }

        // Verify MIME type matches extension
        if (!$this->mimeMatchesExtension($canonicalMimeType, $extension)) {
            throw new Exception('Le type de fichier ne correspond pas à son extension.');
        }

        // Scan file content for malicious patterns
        $this->scanFileContent($file);

        // Additional PDF validation
        if ($canonicalMimeType === 'application/pdf') {
            $this->validatePdf($file);
        }

        return $canonicalMimeType;
    }

    /**
     * Check if MIME type matches the file extension
     */
    private function mimeMatchesExtension(string $mimeType, string $extension): bool
    {
        $mapping = [
            'application/pdf' => ['pdf'],
        ];

        return isset($mapping[$mimeType]) && in_array($extension, $mapping[$mimeType]);
    }

    /**
     * Scan file content for dangerous patterns
     */
    private function scanFileContent(UploadedFile $file): void
    {
        $content = file_get_contents($file->getRealPath());
        
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new Exception('Le fichier contient du contenu potentiellement dangereux.');
            }
        }
    }

    /**
     * Validate PDF file
     */
    private function validatePdf(UploadedFile $file): void
    {
        $content = file_get_contents($file->getRealPath(), false, null, 0, 1024);
        
        // Check PDF header
        if (!str_starts_with($content, '%PDF-')) {
            throw new Exception('Le fichier PDF est invalide ou corrompu.');
        }

        // Check for JavaScript in PDF (basic check)
        $fullContent = file_get_contents($file->getRealPath());
        if (preg_match('/\/JavaScript|\/JS\s/i', $fullContent)) {
            throw new Exception('Les fichiers PDF contenant du JavaScript ne sont pas autorisés.');
        }
    }

    /**
     * Generate a secure random filename
     */
    private function generateSecureFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        return Str::uuid() . '.' . $extension;
    }

    /**
     * Sanitize original filename for storage
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove path separators
        $filename = basename($filename);
        
        // Remove or replace dangerous characters
        $filename = preg_replace('/[^\w\-\.]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 250 - strlen($extension));
            $filename = $name . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Get storage path for a document type
     */
    private function getStoragePath(int $candidatureId, string $type): string
    {
        return "candidatures/{$candidatureId}/{$type}";
    }

    /**
     * Delete a document securely
     */
    public function delete(CandidatureDocument $document): bool
    {
        if (Storage::disk('private')->exists($document->path)) {
            Storage::disk('private')->delete($document->path);
        }

        return $document->delete();
    }

    /**
     * Get file content for download with integrity check
     */
    public function getFileForDownload(CandidatureDocument $document): array
    {
        if (!Storage::disk('private')->exists($document->path)) {
            throw new Exception('Le fichier n\'existe plus.');
        }

        // Verify integrity
        if ($document->hash && !$document->verifyIntegrity()) {
            throw new Exception('L\'intégrité du fichier ne peut pas être vérifiée.');
        }

        return [
            'content' => Storage::disk('private')->get($document->path),
            'mime_type' => $document->mime_type,
            'original_name' => $document->original_name,
        ];
    }

    /**
     * Get file path + metadata for download with integrity check (stream-friendly)
     */
    public function getFileInfoForDownload(CandidatureDocument $document): array
    {
        if (!Storage::disk('private')->exists($document->path)) {
            throw new Exception('Le fichier n\'existe plus.');
        }

        // Verify integrity
        if ($document->hash && !$document->verifyIntegrity()) {
            throw new Exception('L\'intégrité du fichier ne peut pas être vérifiée.');
        }

        return [
            'full_path' => Storage::disk('private')->path($document->path),
            'mime_type' => $document->mime_type,
            'original_name' => $document->original_name,
        ];
    }
}
