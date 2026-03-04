<?php

namespace App\Http\Controllers\Candidat;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureActivite;
use App\Models\CandidatureDocument;
use App\Services\SecureFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    protected SecureFileUploadService $uploadService;

    public function __construct(SecureFileUploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get all documents for current candidature
     */
    public function index(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        $type = $request->query('type');
        
        $query = $candidature->documents();
        
        if ($type) {
            $query->where('type', $type);
        }
        
        $documents = $query->with('activite')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'documents' => $documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'original_name' => $doc->original_name,
                    'mime_type' => $doc->mime_type,
                    'size' => $doc->size,
                    'type' => $doc->type,
                    'is_verified' => $doc->is_verified,
                    'created_at' => $doc->created_at,
                    'activite_id' => $doc->activite_id,
                    'activite' => $doc->activite ? [
                        'category' => $doc->activite->category,
                        'subcategory' => $doc->activite->subcategory,
                    ] : null,
                ];
            }),
        ]);
    }

    /**
     * Upload a document for an activity
     */
    public function uploadForActivite(Request $request, int $activiteId): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        // Verify activite belongs to this candidature
        $activite = $candidature->activites()->find($activiteId);
        
        if (!$activite) {
            return response()->json(['error' => 'Activité non trouvée'], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB
        ], [
            'file.required' => 'Le fichier est requis',
            'file.file' => 'Le fichier est invalide',
            'file.mimes' => 'Seuls les fichiers PDF sont autorisés',
            'file.max' => 'Le fichier ne peut pas dépasser 10 Mo',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');

        // Upload file securely using the service (multiple files per activity allowed)
        try {
            $document = $this->uploadService->upload(
                $file,
                $candidature->id,
                CandidatureDocument::TYPE_ACTIVITE_ATTESTATION,
                $activiteId
            );

            // Update verification status
            $document->update([
                'is_verified' => true,
                'scanned_at' => now(),
            ]);

            return response()->json([
                'message' => 'Document téléversé avec succès',
                'document' => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size' => $document->size,
                    'is_verified' => $document->is_verified,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'candidature_id' => $candidature->id,
                'activite_id' => $activiteId,
            ]);
            
            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Erreur lors du téléversement du fichier'
            ], 422);
        }
    }

    /**
     * Upload a general document (CV, diplomas, etc.)
     */
    public function upload(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf|max:10240',
            'type' => 'required|in:' . implode(',', [
                CandidatureDocument::TYPE_PROFILE_PDF,
                CandidatureDocument::TYPE_ENSEIGNEMENTS_PDF,
                CandidatureDocument::TYPE_PFE_PDF,
                CandidatureDocument::TYPE_SIGNED_DOCUMENT,
                CandidatureDocument::TYPE_ATTESTATION_ENS_PDF,
                CandidatureDocument::TYPE_ATTESTATION_RECH_PDF,
            ]),
        ], [
            'file.required' => 'Le fichier est requis',
            'file.mimes' => 'Seuls les fichiers PDF sont autorisés',
            'file.max' => 'Le fichier ne peut pas dépasser 10 Mo',
            'type.required' => 'Le type de document est requis',
            'type.in' => 'Type de document invalide',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $type = $request->type;

        try {
            $document = $this->uploadService->upload(
                $file,
                $candidature->id,
                $type,
                null
            );

            // Update verification status
            $document->update([
                'is_verified' => true,
                'scanned_at' => now(),
            ]);

            return response()->json([
                'message' => 'Document téléversé avec succès',
                'document' => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size' => $document->size,
                    'type' => $document->type,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'candidature_id' => $candidature->id,
            ]);
            
            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Erreur lors du téléversement du fichier'
            ], 422);
        }
    }

    /**
     * Preview a document inline (PDF viewer)
     */
    public function preview(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $candidature = $this->getCandidature($request);

        if (!$candidature) {
            return response()->json(['error' => 'Candidature non trouvée'], 404);
        }

        $document = $candidature->documents()->find($id);

        if (!$document) {
            return response()->json(['error' => 'Document non trouvé'], 404);
        }

        if ($document->mime_type !== 'application/pdf') {
            return response()->json(['error' => 'Aperçu disponible uniquement pour les fichiers PDF'], 422);
        }

        if (!Storage::disk('private')->exists($document->path)) {
            return response()->json(['error' => 'Fichier non accessible'], 404);
        }

        return response()->stream(function () use ($document) {
            echo Storage::disk('private')->get($document->path);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Download a document
     */
    public function download(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $candidature = $this->getCandidature($request);
        
        if (!$candidature) {
            return response()->json(['error' => 'Candidature non trouvée'], 404);
        }

        $document = $candidature->documents()->find($id);

        if (!$document) {
            return response()->json(['error' => 'Document non trouvé'], 404);
        }

        try {
            $file = $this->uploadService->getFileInfoForDownload($document);

            return response()->download(
                $file['full_path'],
                $file['original_name'],
                ['Content-Type' => $file['mime_type']]
            );
        } catch (\Exception $e) {
            Log::error('Document download failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'document_id' => $document->id,
            ]);
            
            return response()->json(['error' => 'Fichier non accessible: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a document
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $candidature = $this->getCandidature($request);
        
        if (!$candidature) {
            return response()->json(['error' => 'Candidature non trouvée'], 404);
        }

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $document = $candidature->documents()->find($id);

        if (!$document) {
            return response()->json(['error' => 'Document non trouvé'], 404);
        }

        // Delete physical file and record
        $this->uploadService->delete($document);

        return response()->json(['message' => 'Document supprimé']);
    }

    private function getCandidature(Request $request): ?Candidature
    {
        return $request->user()->candidature;
    }

    private function getOrCreateCandidature(Request $request): Candidature
    {
        $user = $request->user();
        $candidature = $user->candidature;
        
        if (!$candidature) {
            $candidature = Candidature::create([
                'user_id' => $user->id,
                'current_step' => 1,
                'status' => Candidature::STATUS_DRAFT,
            ]);
        }
        
        return $candidature;
    }
}
