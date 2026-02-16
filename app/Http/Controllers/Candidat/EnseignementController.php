<?php

namespace App\Http\Controllers\Candidat;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureEnseignement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EnseignementController extends Controller
{
    /**
     * Get all enseignements for current candidature
     */
    public function index(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        $enseignements = $candidature->enseignements()
            ->orderBy('annee_universitaire', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate totals
        $totals = [
            'volume_horaire' => $enseignements->sum('volume_horaire'),
            'equivalent_tp' => $enseignements->sum('equivalent_tp'),
            'count' => $enseignements->count(),
        ];

        // Group by year
        $byYear = $enseignements->groupBy('annee_universitaire')->map(function ($items) {
            return [
                'items' => $items,
                'volume_horaire' => $items->sum('volume_horaire'),
                'equivalent_tp' => $items->sum('equivalent_tp'),
            ];
        });

        return response()->json([
            'enseignements' => $enseignements,
            'totals' => $totals,
            'by_year' => $byYear,
        ]);
    }

    /**
     * Add a new enseignement
     */
    public function store(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $validator = Validator::make($request->all(), [
            'annee_universitaire' => 'required|string|max:20',
            'intitule' => 'required|string|max:500',
            'type_enseignement' => 'required|in:CM,TD,TP',
            'type_module' => 'required|in:Module,Element de module',
            'niveau' => 'required|string|max:50',
            'volume_horaire' => 'required|integer|min:1|max:1000',
        ], [
            'annee_universitaire.required' => 'L\'année universitaire est requise',
            'intitule.required' => 'L\'intitulé est requis',
            'type_enseignement.required' => 'Le type d\'enseignement est requis',
            'type_enseignement.in' => 'Le type d\'enseignement doit être CM, TD ou TP',
            'type_module.required' => 'Le type de module est requis',
            'niveau.required' => 'Le niveau est requis',
            'volume_horaire.required' => 'Le volume horaire est requis',
            'volume_horaire.min' => 'Le volume horaire doit être supérieur à 0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['candidature_id'] = $candidature->id;
        $data['equivalent_tp'] = CandidatureEnseignement::calculateEquivalentTp(
            $data['type_enseignement'],
            $data['volume_horaire']
        );

        $enseignement = CandidatureEnseignement::create($data);

        // Update step
        if ($candidature->current_step < 3) {
            $candidature->update(['current_step' => 3]);
        }

        return response()->json([
            'message' => 'Enseignement ajouté',
            'enseignement' => $enseignement,
        ], 201);
    }

    /**
     * Delete an enseignement
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $enseignement = $candidature->enseignements()->find($id);

        if (!$enseignement) {
            return response()->json(['error' => 'Enseignement non trouvé'], 404);
        }

        $enseignement->delete();

        return response()->json(['message' => 'Enseignement supprimé']);
    }

    /**
     * Bulk save enseignements
     */
    public function bulkSave(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $validator = Validator::make($request->all(), [
            'enseignements' => 'required|array',
            'enseignements.*.annee_universitaire' => 'required_with:enseignements.*|string|max:20',
            'enseignements.*.intitule' => 'required_with:enseignements.*|string|max:500',
            'enseignements.*.type_enseignement' => 'required_with:enseignements.*|in:CM,TD,TP',
            'enseignements.*.type_module' => 'required_with:enseignements.*|in:Module,Element de module',
            'enseignements.*.niveau' => 'required_with:enseignements.*|string|max:50',
            'enseignements.*.volume_horaire' => 'required_with:enseignements.*|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Filter out invalid entries
        $validEnseignements = collect($request->enseignements ?? [])->filter(function ($item) {
            return isset($item['annee_universitaire']) && 
                   isset($item['intitule']) && 
                   isset($item['type_enseignement']) && 
                   isset($item['type_module']) && 
                   isset($item['niveau']) && 
                   isset($item['volume_horaire']) &&
                   is_numeric($item['volume_horaire']) &&
                   $item['volume_horaire'] > 0;
        })->values()->toArray();

        // Delete existing and recreate
        $candidature->enseignements()->delete();

        $enseignements = collect($validEnseignements)->map(function ($item) use ($candidature) {
            return CandidatureEnseignement::create([
                'candidature_id' => $candidature->id,
                'annee_universitaire' => $item['annee_universitaire'],
                'intitule' => $item['intitule'],
                'type_enseignement' => $item['type_enseignement'],
                'type_module' => $item['type_module'],
                'niveau' => $item['niveau'],
                'volume_horaire' => $item['volume_horaire'],
                'equivalent_tp' => CandidatureEnseignement::calculateEquivalentTp(
                    $item['type_enseignement'],
                    $item['volume_horaire']
                ),
            ]);
        });

        // Only update step if we actually saved enseignements
        if ($enseignements->isNotEmpty() && $candidature->current_step < 3) {
            $candidature->update(['current_step' => 3]);
        }

        return response()->json([
            'message' => $enseignements->isNotEmpty() ? 'Enseignements enregistrés' : 'Aucun enseignement à enregistrer',
            'enseignements' => $enseignements,
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
