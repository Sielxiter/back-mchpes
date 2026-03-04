<?php

namespace App\Http\Controllers\Candidat;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureDocument;
use App\Services\PdfGenerationService;
use App\Services\SecureFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfGeneratorController extends Controller
{
    protected PdfGenerationService $pdfService;
    protected SecureFileUploadService $uploadService;

    public function __construct(PdfGenerationService $pdfService, SecureFileUploadService $uploadService)
    {
        $this->pdfService = $pdfService;
        $this->uploadService = $uploadService;
    }

    /**
     * Generate a PDF of the given type and stream it for download.
     * GET /candidat/documents/generate/{type}
     */
    public function generate(Request $request, string $type): StreamedResponse|JsonResponse
    {
        if (!in_array($type, PdfGenerationService::validTypes())) {
            return response()->json(['error' => 'Type de document invalide'], 422);
        }

        $candidature = $this->getCandidature($request);

        if (!$candidature) {
            return response()->json(['error' => 'Candidature non trouvée'], 404);
        }

        try {
            $pdfContent = $this->pdfService->generate($type, $candidature);

            $filename = match ($type) {
                'profile' => 'formulaire-candidature.pdf',
                'enseignements' => 'recapitulatif-enseignements.pdf',
                'pfe' => 'recapitulatif-pfes.pdf',
                'attestation_ens' => 'attestation-activites-enseignement.pdf',
                'attestation_rech' => 'attestation-activites-recherche.pdf',
            };

            return response()->stream(function () use ($pdfContent) {
                echo $pdfContent;
            }, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Content-Length' => strlen($pdfContent),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]);
        } catch (\Exception $e) {
            Log::error('PDF generation failed', [
                'type' => $type,
                'candidature_id' => $candidature->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de la génération du PDF',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a PDF and store it permanently in the system.
     * POST /candidat/documents/generate/{type}/store
     */
    public function generateAndStore(Request $request, string $type): JsonResponse
    {
        if (!in_array($type, PdfGenerationService::validTypes())) {
            return response()->json(['error' => 'Type de document invalide'], 422);
        }

        $candidature = $this->getCandidature($request);

        if (!$candidature) {
            return response()->json(['error' => 'Candidature non trouvée'], 404);
        }

        try {
            $document = $this->pdfService->generateAndStore($type, $candidature);

            return response()->json([
                'message' => 'Document généré et enregistré avec succès',
                'document' => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'original_name' => $document->original_name,
                    'size' => $document->size,
                    'created_at' => $document->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('PDF generation and storage failed', [
                'type' => $type,
                'candidature_id' => $candidature->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de la génération du PDF',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate all 5 PDFs at once and store them.
     * POST /candidat/documents/generate-all
     */
    public function generateAll(Request $request): JsonResponse
    {
        $candidature = $this->getCandidature($request);

        if (!$candidature) {
            return response()->json(['error' => 'Candidature non trouvée'], 404);
        }

        try {
            $results = $this->pdfService->generateAll($candidature);

            $docs = [];
            foreach ($results as $key => $doc) {
                $docs[$key] = [
                    'id' => $doc->id,
                    'type' => $doc->type,
                    'original_name' => $doc->original_name,
                    'size' => $doc->size,
                    'created_at' => $doc->created_at,
                ];
            }

            return response()->json([
                'message' => 'Tous les documents ont été générés avec succès',
                'documents' => $docs,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Generate all PDFs failed', [
                'candidature_id' => $candidature->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de la génération des documents',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the status of all generated + signed documents.
     * GET /candidat/documents/generated-status
     */
    public function status(Request $request): JsonResponse
    {
        $candidature = $this->getCandidature($request);

        if (!$candidature) {
            return response()->json(['error' => 'Candidature non trouvée'], 404);
        }

        $status = $this->pdfService->getDocumentStatus($candidature);

        return response()->json(['status' => $status]);
    }

    /**
     * Upload a signed (legalized) version of a generated document.
     * POST /candidat/documents/signed/{type}
     */
    public function uploadSigned(Request $request, string $type): JsonResponse
    {
        if (!in_array($type, PdfGenerationService::validTypes())) {
            return response()->json(['error' => 'Type de document invalide'], 422);
        }

        $candidature = $this->getCandidature($request);

        if (!$candidature) {
            return response()->json(['error' => 'Candidature non trouvée'], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf|max:10240',
        ], [
            'file.required' => 'Le fichier est requis',
            'file.mimes' => 'Seuls les fichiers PDF sont autorisés',
            'file.max' => 'Le fichier ne peut pas dépasser 10 Mo',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Determine the document types
        $generatedTypes = [
            'profile' => CandidatureDocument::TYPE_PROFILE_PDF,
            'enseignements' => CandidatureDocument::TYPE_ENSEIGNEMENTS_PDF,
            'pfe' => CandidatureDocument::TYPE_PFE_PDF,
            'attestation_ens' => CandidatureDocument::TYPE_ATTESTATION_ENS_PDF,
            'attestation_rech' => CandidatureDocument::TYPE_ATTESTATION_RECH_PDF,
        ];

        $signedTypes = [
            'profile' => CandidatureDocument::TYPE_SIGNED_PROFILE,
            'enseignements' => CandidatureDocument::TYPE_SIGNED_ENSEIGNEMENTS,
            'pfe' => CandidatureDocument::TYPE_SIGNED_PFE,
            'attestation_ens' => CandidatureDocument::TYPE_SIGNED_ATTESTATION_ENS,
            'attestation_rech' => CandidatureDocument::TYPE_SIGNED_ATTESTATION_RECH,
        ];

        $generatedDocType = $generatedTypes[$type];
        $signedDocType = $signedTypes[$type];

        // Find the generated document to link to
        $generatedDoc = $candidature->documents()->where('type', $generatedDocType)->first();

        try {
            $file = $request->file('file');

            // Remove existing signed version of this type
            $existingSigned = $candidature->documents()->where('type', $signedDocType)->first();
            if ($existingSigned) {
                $this->uploadService->delete($existingSigned);
            }

            // Upload the signed file
            $document = $this->uploadService->upload(
                $file,
                $candidature->id,
                $signedDocType,
                null
            );

            // Link to generated document and verify
            $document->update([
                'generated_document_id' => $generatedDoc?->id,
                'is_verified' => true,
                'scanned_at' => now(),
            ]);

            return response()->json([
                'message' => 'Document signé téléversé avec succès',
                'document' => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'original_name' => $document->original_name,
                    'size' => $document->size,
                    'created_at' => $document->created_at,
                    'is_verified' => $document->is_verified,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Signed document upload failed', [
                'type' => $type,
                'candidature_id' => $candidature->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Erreur lors du téléversement du document signé',
            ], 422);
        }
    }

    /**
     * Delete a signed document.
     * DELETE /candidat/documents/signed/{type}
     */
    public function deleteSigned(Request $request, string $type): JsonResponse
    {
        if (!in_array($type, PdfGenerationService::validTypes())) {
            return response()->json(['error' => 'Type de document invalide'], 422);
        }

        $candidature = $this->getCandidature($request);

        if (!$candidature) {
            return response()->json(['error' => 'Candidature non trouvée'], 404);
        }

        $signedTypes = [
            'profile' => CandidatureDocument::TYPE_SIGNED_PROFILE,
            'enseignements' => CandidatureDocument::TYPE_SIGNED_ENSEIGNEMENTS,
            'pfe' => CandidatureDocument::TYPE_SIGNED_PFE,
            'attestation_ens' => CandidatureDocument::TYPE_SIGNED_ATTESTATION_ENS,
            'attestation_rech' => CandidatureDocument::TYPE_SIGNED_ATTESTATION_RECH,
        ];

        $signedDocType = $signedTypes[$type];
        $document = $candidature->documents()->where('type', $signedDocType)->first();

        if (!$document) {
            return response()->json(['error' => 'Document signé non trouvé'], 404);
        }

        $this->uploadService->delete($document);

        return response()->json(['message' => 'Document signé supprimé']);
    }

    private function getCandidature(Request $request): ?Candidature
    {
        return $request->user()->candidature;
    }
}
