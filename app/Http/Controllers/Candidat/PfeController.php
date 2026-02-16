<?php

namespace App\Http\Controllers\Candidat;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidaturePfe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PfeController extends Controller
{
    /**
     * Get all PFE records for current candidature
     */
    public function index(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        $pfes = $candidature->pfes()
            ->orderBy('annee_universitaire', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate totals
        $totals = [
            'volume_horaire' => $pfes->sum('volume_horaire'),
            'count' => $pfes->count(),
        ];

        // Group by year
        $byYear = $pfes->groupBy('annee_universitaire')->map(function ($items) {
            return [
                'items' => $items,
                'volume_horaire' => $items->sum('volume_horaire'),
            ];
        });

        // Group by niveau
        $byNiveau = $pfes->groupBy('niveau')->map(function ($items) {
            return [
                'count' => $items->count(),
                'volume_horaire' => $items->sum('volume_horaire'),
            ];
        });

        return response()->json([
            'pfes' => $pfes,
            'totals' => $totals,
            'by_year' => $byYear,
            'by_niveau' => $byNiveau,
        ]);
    }

    /**
     * Add a new PFE record
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
            'niveau' => 'required|in:DUT,Licence,Master,Ingénieur,Doctorat,Autre',
            'volume_horaire' => 'required|integer|min:1|max:1000',
        ], [
            'annee_universitaire.required' => 'L\'année universitaire est requise',
            'intitule.required' => 'L\'intitulé du PFE est requis',
            'niveau.required' => 'Le niveau est requis',
            'niveau.in' => 'Le niveau doit être DUT, Licence, Master, Ingénieur, Doctorat ou Autre',
            'volume_horaire.required' => 'Le volume horaire est requis',
            'volume_horaire.min' => 'Le volume horaire doit être supérieur à 0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['candidature_id'] = $candidature->id;

        $pfe = CandidaturePfe::create($data);

        // Update step
        if ($candidature->current_step < 4) {
            $candidature->update(['current_step' => 4]);
        }

        return response()->json([
            'message' => 'Encadrement PFE ajouté',
            'pfe' => $pfe,
        ], 201);
    }

    /**
     * Update a PFE record
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $pfe = $candidature->pfes()->find($id);

        if (!$pfe) {
            return response()->json(['error' => 'PFE non trouvé'], 404);
        }

        $validator = Validator::make($request->all(), [
            'annee_universitaire' => 'sometimes|string|max:20',
            'intitule' => 'sometimes|string|max:500',
            'niveau' => 'sometimes|in:DUT,Licence,Master,Ingénieur,Doctorat,Autre',
            'volume_horaire' => 'sometimes|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pfe->update($validator->validated());

        return response()->json([
            'message' => 'Encadrement PFE modifié',
            'pfe' => $pfe->fresh(),
        ]);
    }

    /**
     * Delete a PFE record
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $pfe = $candidature->pfes()->find($id);

        if (!$pfe) {
            return response()->json(['error' => 'PFE non trouvé'], 404);
        }

        $pfe->delete();

        return response()->json(['message' => 'Encadrement PFE supprimé']);
    }

    /**
     * Bulk save PFE records
     */
    public function bulkSave(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $validator = Validator::make($request->all(), [
            'pfes' => 'required|array',
            'pfes.*.annee_universitaire' => 'required|string|max:20',
            'pfes.*.intitule' => 'required|string|max:500',
            'pfes.*.niveau' => 'required|in:DUT,Licence,Master,Ingénieur,Doctorat,Autre',
            'pfes.*.volume_horaire' => 'required|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Delete existing and recreate
        $candidature->pfes()->delete();

        $pfes = collect($request->pfes)->map(function ($item) use ($candidature) {
            return CandidaturePfe::create([
                'candidature_id' => $candidature->id,
                'annee_universitaire' => $item['annee_universitaire'],
                'intitule' => $item['intitule'],
                'niveau' => $item['niveau'],
                'volume_horaire' => $item['volume_horaire'],
            ]);
        });

        if ($candidature->current_step < 4) {
            $candidature->update(['current_step' => 4]);
        }

        return response()->json([
            'message' => 'Encadrements PFE enregistrés',
            'pfes' => $pfes,
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
