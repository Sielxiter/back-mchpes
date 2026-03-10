<?php

namespace App\Http\Controllers\Candidat;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureActivite;
use App\Services\SecureFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ActiviteController extends Controller
{
    protected SecureFileUploadService $uploadService;

    public function __construct(SecureFileUploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    // Category definitions for validation
    const ENSEIGNEMENT_CATEGORIES = [
        'A/1' => [
            'Ouvrage pédagogique et/ou didactique (auteur ou co-auteur) (ISBN ou Maison d\'édition reconnue)',
            'Manuel (exercices corrigés, annales examens corrigés, …)',
            'Polycopiés pédagogiques',
            'Manuel en méthodologie didactique lecture et analyse',
            'Montages expérimentaux',
            'Support audio visuel',
            'Support électronique Diaporamas',
            'Didacticiels',
            'Page web à caractère pédagogique',
            'MOOC : Production de contenus en ligne e-learning',
        ],
        'A/2' => [
            'Mémoire de PFE Licence',
            'TFE Ingénieur ou équivalent du même niveau',
            'Mémoire de Master, PFE, DUT, Bac + 5',
            'Rapport de stage ou de visite de terrain',
            'Formation de formateurs',
            'Formation du Personnel administratif et/ou technique',
        ],
        'A/3' => [
            'Coordinateur Master en formation initiale, filière d\'ingénieur ou autre filière Bac+5',
            'Coordinateur de filière de Licence Professionnelle, DUT ou FUE',
            'Coordinateur de Module (max 2 modules)',
            'Chef de Département',
            'Vice-doyen (non cumulable avec commission permanente)',
            'Membre d\'une commission permanente de l\'établissement',
            'Membre du conseil de coordination',
            'Membre d\'une commission permanente du conseil de coordination',
            'Membre d\'une commission ad hoc (recrutement, marchés, évaluation institutionnelle, expertise ou autres)',
        ],
    ];

    const RECHERCHE_CATEGORIES = [
        'B/1' => [
            'Niveau 1: Impact factor < 1',
            'Niveau 2: Impact factor ≤ 5',
            'Niveau 3: Impact factor > 5',
            'Internationale: Publication dans des revues scientifiques à comité de lecture, dans des actes de congrès ou chapitre de livre',
            'Nationale: Publication dans des revues scientifiques à comité de lecture, dans des actes de congrès ou chapitre de livre',
            'Ouvrage dans la spécialité ISBN, édité par une maison d\'édition',
            'Ouvrage dans la spécialité ISBN, édité sur le compte de l\'auteur',
            'Chapitre d\'un ouvrage collectif édité par une maison d\'édition',
            'Congrès international',
            'Congrès national',
        ],
        'B/2' => [
            'Doctorats encadrés soutenus',
            'Doctorats encadrés non soutenus',
            'Encadrement et/ou Co-Encadrement Master',
            'Président',
            'Rapporteur',
            'Examinateur',
        ],
        'B/3' => [
            'Centre de recherche - Responsable',
            'Centre de recherche - Membre',
            'Pôle de compétence - Responsable',
            'Pôle de compétence - Membre',
            'Association connaissance & pensée - Responsable',
            'Association connaissance & pensée - Membre',
            'Editeur d\'un journal scientifique',
            'Membre de l\'éditorial d\'un journal scientifique',
            'Référé d\'un journal scientifique',
            'Expertise non rémunérée de projet de recherche scientifique',
        ],
        'B/4' => [
            'Manifestation Internationale - Responsable',
            'Manifestation Internationale - Membre organisateur',
            'Manifestation Nationale - Responsable',
            'Manifestation Nationale - Membre',
            'Prix et distinction',
            'Projet Recherche & Développement avec le secteur privé',
            'Brevet',
            'Incubation de projet Recherche & Développement',
            'Création de start up (entreprise)',
        ],
    ];

    /**
     * Get all activities by type
     */
    public function index(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        $type = $request->query('type', 'enseignement');
        
        $activites = $candidature->activites()
            ->where('type', $type)
            ->with('documents')
            ->orderBy('category')
            ->orderBy('subcategory')
            ->get();

        // Group by category
        $byCategory = $activites->groupBy('category')->map(function ($items, $category) {
            return [
                'items' => $items,
                'total_count' => $items->sum('count'),
                'has_all_documents' => $items->every(fn($item) => $item->documents->isNotEmpty()),
            ];
        });

        return response()->json([
            'activites' => $activites,
            'by_category' => $byCategory,
            'categories' => $type === 'enseignement' 
                ? self::ENSEIGNEMENT_CATEGORIES 
                : self::RECHERCHE_CATEGORIES,
        ]);
    }

    /**
     * Save/update activities (upsert)
     */
    public function save(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:enseignement,recherche',
            'category' => 'required|string|max:10',
            'subcategory' => 'required|string|max:500',
            'count' => 'required|integer|min:0|max:999',
        ], [
            'type.required' => 'Le type d\'activité est requis',
            'type.in' => 'Le type doit être enseignement ou recherche',
            'category.required' => 'La catégorie est requise',
            'subcategory.required' => 'La sous-catégorie est requise',
            'count.required' => 'Le nombre est requis',
            'count.min' => 'Le nombre ne peut pas être négatif',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Validate category/subcategory combination
        $categories = $data['type'] === 'enseignement' 
            ? self::ENSEIGNEMENT_CATEGORIES 
            : self::RECHERCHE_CATEGORIES;

        if (!isset($categories[$data['category']]) || 
            !in_array($data['subcategory'], $categories[$data['category']])) {
            return response()->json([
                'error' => 'Combinaison catégorie/sous-catégorie invalide'
            ], 422);
        }

        // Upsert (update or insert)
        $activite = CandidatureActivite::updateOrCreate(
            [
                'candidature_id' => $candidature->id,
                'type' => $data['type'],
                'category' => $data['category'],
                'subcategory' => $data['subcategory'],
            ],
            ['count' => $data['count']]
        );

        // Update step based on type
        $stepTarget = $data['type'] === 'enseignement' ? 5 : 6;
        if ($candidature->current_step < $stepTarget) {
            $candidature->update(['current_step' => $stepTarget]);
        }

        return response()->json([
            'message' => 'Activité enregistrée',
            'activite' => $activite->load('documents'),
        ]);
    }

    /**
     * Bulk save activities
     */
    public function bulkSave(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:enseignement,recherche',
            'activites' => 'required|array',
            'activites.*.category' => 'required_with:activites.*|string|max:10',
            'activites.*.subcategory' => 'required_with:activites.*|string|max:500',
            'activites.*.count' => 'required_with:activites.*|integer|min:0|max:999',
        ], [
            'type.required' => 'Le type d\'activité est requis',
            'type.in' => 'Le type doit être enseignement ou recherche',
            'activites.required' => 'La liste des activités est requise',
            'activites.array' => 'Les activités doivent être un tableau',
            'activites.*.category.required_with' => 'La catégorie est requise pour chaque activité',
            'activites.*.subcategory.required_with' => 'La sous-catégorie est requise pour chaque activité',
            'activites.*.count.required_with' => 'Le nombre est requis pour chaque activité',
            'activites.*.count.integer' => 'Le nombre doit être un entier',
            'activites.*.count.min' => 'Le nombre ne peut pas être négatif',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Filter out entries with count 0 or missing required fields
        $validActivites = collect($request->activites ?? [])->filter(function ($item) {
            return isset($item['category']) && 
                   isset($item['subcategory']) && 
                   isset($item['count']) && 
                   is_numeric($item['count']) &&
                   $item['count'] > 0;
        })->values()->toArray();

        $type = $request->type;
        $categories = $type === 'enseignement' 
            ? self::ENSEIGNEMENT_CATEGORIES 
            : self::RECHERCHE_CATEGORIES;

        // Delete existing of this type (but keep documents - they'll be orphaned if not reassociated)
        // Actually, we should preserve documents by keeping existing records if they have documents
        $existingWithDocs = $candidature->activites()
            ->where('type', $type)
            ->whereHas('documents')
            ->pluck('id', 'subcategory')
            ->toArray();

        // Delete only those without documents
        $candidature->activites()
            ->where('type', $type)
            ->whereDoesntHave('documents')
            ->delete();

        $saved = [];
        foreach ($validActivites as $item) {
            // Validate combination
            if (!isset($categories[$item['category']]) || 
                !in_array($item['subcategory'], $categories[$item['category']])) {
                continue; // Skip invalid
            }

            $activite = CandidatureActivite::updateOrCreate(
                [
                    'candidature_id' => $candidature->id,
                    'type' => $type,
                    'category' => $item['category'],
                    'subcategory' => $item['subcategory'],
                ],
                ['count' => $item['count']]
            );

            $saved[] = $activite;
        }

        // Only update step if we actually saved activities
        if (count($saved) > 0) {
            $stepTarget = $type === 'enseignement' ? 5 : 6;
            if ($candidature->current_step < $stepTarget) {
                $candidature->update(['current_step' => $stepTarget]);
            }
        }

        $activites = $candidature->activites()
            ->where('type', $type)
            ->with('documents')
            ->get();

        return response()->json([
            'message' => count($saved) > 0 ? 'Activités enregistrées' : 'Aucune activité à enregistrer',
            'activites' => $activites,
        ]);
    }

    /**
     * Delete an activity
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $activite = $candidature->activites()->find($id);

        if (!$activite) {
            return response()->json(['error' => 'Activité non trouvée'], 404);
        }

        // Delete associated documents if any
        foreach ($activite->documents as $doc) {
            $this->uploadService->delete($doc);
        }

        $activite->delete();

        return response()->json(['message' => 'Activité supprimée']);
    }

    /**
     * Get category definitions
     */
    public function categories(Request $request): JsonResponse
    {
        $type = $request->query('type', 'enseignement');
        
        return response()->json([
            'categories' => $type === 'enseignement' 
                ? self::ENSEIGNEMENT_CATEGORIES 
                : self::RECHERCHE_CATEGORIES,
        ]);
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
