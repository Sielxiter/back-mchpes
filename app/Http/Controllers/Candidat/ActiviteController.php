<?php

namespace App\Http\Controllers\Candidat;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureActivite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ActiviteController extends Controller
{
    // Category definitions for validation
    const ENSEIGNEMENT_CATEGORIES = [
        'A/1' => [
            'Conception et montage d\'une filière accréditée comme coordonnateur',
            'Coordination d\'une filière accréditée ou d\'un établissement',
            'Préparation de cours ou TD ou TP d\'un module nouveaux',
            'Préparation de supports et polycopiés de cours ou TD ou TP',
            'Participation aux travaux des jurys au niveau national',
            'Responsable d\'un module',
        ],
        'A/2' => [
            'Encadrement de PFE Licence, Master, Ingénieur',
            'Encadrement de stages et visites de terrain',
            'Formation de formateurs et personnel',
        ],
        'A/3' => [
            'Tutorat d\'étudiants (PFE, stages...)',
            'Organisation de manifestations scientifiques ou pédagogiques',
            'Participation active aux travaux des commissions pédagogiques',
        ],
    ];

    const RECHERCHE_CATEGORIES = [
        'B/1' => [
            'Publication dans une revue indexée',
            'Brevet déposé ou exploité',
            'Direction de thèse soutenue',
            'Co-direction de thèse soutenue',
        ],
        'B/2' => [
            'Publication dans les actes de congrès indexés',
            'Publication dans une revue spécialisée non indexée',
            'Direction de thèses en cours d\'un doctorant inscrit',
        ],
        'B/3' => [
            'Participation à des projets de recherche financés (CNRST, International...)',
            'Création ou participation à la création d\'une structure de recherche accréditée',
            'Communication orale ou poster dans un congrès',
        ],
        'B/4' => [
            'Responsabilité de structure de recherche accréditée comme directeur',
            'Responsabilité de structure de recherche accréditée comme chef d\'équipe',
            'Rédaction de rapports d\'expertise ou de rapports techniques',
            'Évaluation d\'articles scientifiques (reviewer)',
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
            ->with('document')
            ->orderBy('category')
            ->orderBy('subcategory')
            ->get();

        // Group by category
        $byCategory = $activites->groupBy('category')->map(function ($items, $category) {
            return [
                'items' => $items,
                'total_count' => $items->sum('count'),
                'has_all_documents' => $items->every(fn($item) => $item->document !== null),
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
            'activite' => $activite->load('document'),
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
            ->whereHas('document')
            ->pluck('id', 'subcategory')
            ->toArray();

        // Delete only those without documents
        $candidature->activites()
            ->where('type', $type)
            ->whereDoesntHave('document')
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
            ->with('document')
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

        // Delete associated document if exists
        if ($activite->document) {
            $activite->document->delete();
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
