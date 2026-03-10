<?php

namespace App\Services;

use App\Models\Candidature;
use App\Models\CandidatureDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfGenerationService
{
    /**
     * Map from generation type key to document type constant.
     */
    private const TYPE_MAP = [
        'profile' => CandidatureDocument::TYPE_PROFILE_PDF,
        'enseignements' => CandidatureDocument::TYPE_ENSEIGNEMENTS_PDF,
        'pfe' => CandidatureDocument::TYPE_PFE_PDF,
        'attestation_ens' => CandidatureDocument::TYPE_ATTESTATION_ENS_PDF,
        'attestation_rech' => CandidatureDocument::TYPE_ATTESTATION_RECH_PDF,
    ];

    private const FILENAME_MAP = [
        'profile' => 'formulaire-candidature.pdf',
        'enseignements' => 'recapitulatif-enseignements.pdf',
        'pfe' => 'recapitulatif-pfes.pdf',
        'attestation_ens' => 'attestation-activites-enseignement.pdf',
        'attestation_rech' => 'attestation-activites-recherche.pdf',
    ];

    private const TITLE_MAP = [
        'attestation_ens' => "Attestation des activités d'enseignement",
        'attestation_rech' => 'Attestation des activités de recherche',
    ];

    /**
     * Get all valid generation type keys.
     */
    public static function validTypes(): array
    {
        return array_keys(self::TYPE_MAP);
    }

    /**
     * Generate a PDF for the given type and candidature.
     * Returns the raw PDF content as string.
     */
    public function generate(string $typeKey, Candidature $candidature): string
    {
        $data = $this->gatherData($typeKey, $candidature);
        $view = $this->getViewName($typeKey);

        $pdf = Pdf::loadView($view, $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate a PDF and store it as a CandidatureDocument record.
     * Replaces any existing generated document of the same type.
     */
    public function generateAndStore(string $typeKey, Candidature $candidature): CandidatureDocument
    {
        $pdfContent = $this->generate($typeKey, $candidature);
        $docType = self::TYPE_MAP[$typeKey];
        $filename = self::FILENAME_MAP[$typeKey];

        // Remove old generated document of this type (if any)
        $existing = $candidature->documents()->where('type', $docType)->first();
        if ($existing) {
            if (Storage::disk('private')->exists($existing->path)) {
                Storage::disk('private')->delete($existing->path);
            }
            $existing->delete();
        }

        // Store new PDF
        $storedName = Str::uuid() . '.pdf';
        $path = "candidatures/{$candidature->id}/{$docType}/{$storedName}";

        Storage::disk('private')->put($path, $pdfContent);

        $hash = hash('sha256', $pdfContent);

        return CandidatureDocument::create([
            'candidature_id' => $candidature->id,
            'activite_id' => null,
            'generated_document_id' => null,
            'type' => $docType,
            'original_name' => $filename,
            'stored_name' => $storedName,
            'mime_type' => 'application/pdf',
            'size' => strlen($pdfContent),
            'path' => $path,
            'hash' => $hash,
            'is_verified' => true,
            'scanned_at' => now(),
        ]);
    }

    /**
     * Generate all 5 PDFs for a candidature.
     */
    public function generateAll(Candidature $candidature): array
    {
        $results = [];
        foreach (self::TYPE_MAP as $key => $type) {
            $results[$key] = $this->generateAndStore($key, $candidature);
        }
        return $results;
    }

    /**
     * Get the status of all generated and signed documents for a candidature.
     */
    public function getDocumentStatus(Candidature $candidature): array
    {
        $status = [];
        $documents = $candidature->documents()
            ->whereIn('type', array_merge(
                array_values(self::TYPE_MAP),
                array_values(CandidatureDocument::SIGNED_TYPE_MAP)
            ))
            ->get();

        foreach (self::TYPE_MAP as $key => $docType) {
            $generated = $documents->where('type', $docType)->first();
            $signedType = CandidatureDocument::SIGNED_TYPE_MAP[$docType] ?? null;
            $signed = $signedType ? $documents->where('type', $signedType)->first() : null;

            $status[$key] = [
                'type_key' => $key,
                'doc_type' => $docType,
                'label' => self::FILENAME_MAP[$key],
                'generated' => $generated ? [
                    'id' => $generated->id,
                    'original_name' => $generated->original_name,
                    'size' => $generated->size,
                    'created_at' => $generated->created_at,
                ] : null,
                'signed_type' => $signedType,
                'signed' => $signed ? [
                    'id' => $signed->id,
                    'original_name' => $signed->original_name,
                    'size' => $signed->size,
                    'created_at' => $signed->created_at,
                    'is_verified' => $signed->is_verified,
                ] : null,
            ];
        }

        return $status;
    }

    /**
     * Gather template data for each PDF type.
     */
    private function gatherData(string $typeKey, Candidature $candidature): array
    {
        $common = [
            'candidature_id' => $candidature->id,
            'generated_date' => now()->format('d/m/Y'),
            'reference' => 'CAND-' . str_pad($candidature->id, 4, '0', STR_PAD_LEFT),
            'profile' => $candidature->profile,
            'signature' => $candidature->signature,
        ];

        switch ($typeKey) {
            case 'profile':
                $profile = $candidature->profile;
                return array_merge($common, [
                    'profile' => $profile,
                    'user' => $candidature->user,
                ]);

            case 'enseignements':
                $enseignements = $candidature->enseignements()->orderBy('annee_universitaire', 'desc')->get();
                $byYear = $enseignements->groupBy('annee_universitaire')->map(function ($items) {
                    return [
                        'items' => $items,
                        'volume_horaire' => $items->sum('volume_horaire'),
                        'equivalent_tp' => $items->sum('equivalent_tp'),
                    ];
                });
                return array_merge($common, [
                    'enseignements' => $enseignements,
                    'by_year' => $byYear,
                    'totals' => [
                        'volume_horaire' => $enseignements->sum('volume_horaire'),
                        'equivalent_tp' => $enseignements->sum('equivalent_tp'),
                    ],
                ]);

            case 'pfe':
                $pfes = $candidature->pfes()->orderBy('annee_universitaire', 'desc')->get();
                return array_merge($common, [
                    'pfes' => $pfes,
                    'totals' => [
                        'volume_horaire' => $pfes->sum('volume_horaire'),
                        'count' => $pfes->count(),
                    ],
                ]);

            case 'attestation_ens':
                return $this->gatherAttestationData($candidature, 'enseignement', $common);

            case 'attestation_rech':
                return $this->gatherAttestationData($candidature, 'recherche', $common);

            default:
                throw new \InvalidArgumentException("Unknown PDF type: {$typeKey}");
        }
    }

    private function gatherAttestationData(Candidature $candidature, string $activityType, array $common): array
    {
        $activites = $candidature->activites()->where('type', $activityType)->get();
        $titleKey = $activityType === 'enseignement' ? 'attestation_ens' : 'attestation_rech';

        return array_merge($common, [
            'title' => self::TITLE_MAP[$titleKey],
            'activity_type' => $activityType,
            'activites' => $activites,
            'profile' => $candidature->profile,
        ]);
    }

    private function getViewName(string $typeKey): string
    {
        return match ($typeKey) {
            'profile' => 'pdf.profile',
            'enseignements' => 'pdf.enseignements',
            'pfe' => 'pdf.pfes',
            'attestation_ens', 'attestation_rech' => 'pdf.attestation',
        };
    }
}
