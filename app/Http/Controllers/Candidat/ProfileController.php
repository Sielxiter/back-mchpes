<?php

namespace App\Http\Controllers\Candidat;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get profile data
     */
    public function show(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);
        $user = $request->user();
        $profile = $candidature->profile;

        // Pre-fill from user data if profile doesn't exist
        if (!$profile) {
            $nameParts = explode(' ', $user->name, 2);
            
            return response()->json([
                'profile' => null,
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }

        return response()->json([
            'profile' => $profile,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Save profile data
     */
    public function store(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);

        if (!$candidature->canBeEdited()) {
            return response()->json(['error' => 'La candidature est verrouillée'], 422);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'nom_ar' => 'required|string|max:255',
            'prenom_ar' => 'required|string|max:255',
            'cin' => 'required|string|max:50',
            'email' => 'nullable|email|max:255', // Optional, will use user email if not provided
            'date_naissance' => 'required|date|before:today',
            'etablissement' => 'required|string|max:255',
            'ville' => 'required|string|max:255',
            'departement' => 'required|string|max:255',
            'grade_actuel' => 'required|string|max:255',
            'date_recrutement_es' => 'required|date|before_or_equal:today',
            'date_recrutement_fp' => 'nullable|date|before_or_equal:today',
            'numero_som' => 'nullable|string|max:50',
            'telephone' => 'required|string|max:20',
            'specialite' => 'required|string|max:255',
            'date_soutenance_habilitation' => 'nullable|date|before_or_equal:today',
            'exactitude_info' => 'nullable|boolean',
            'acceptation_termes' => 'nullable|boolean',
            // Ignore fields that don't exist in schema
            'a_demande_avancement' => 'nullable',
            'a_dossier_en_cours' => 'nullable',
        ], [
            'nom.required' => 'Le nom est requis',
            'prenom.required' => 'Le prénom est requis',
            'nom_ar.required' => 'الاسم العائلي مطلوب',
            'prenom_ar.required' => 'الاسم الشخصي مطلوب',
            'cin.required' => 'Le CIN est requis',
            'date_naissance.required' => 'La date de naissance est requise',
            'date_naissance.before' => 'La date de naissance doit être dans le passé',
            'etablissement.required' => 'L\'établissement est requis',
            'ville.required' => 'La ville est requise',
            'departement.required' => 'Le département est requis',
            'grade_actuel.required' => 'Le grade actuel est requis',
            'date_recrutement_es.required' => 'La date de recrutement est requise',
            'numero_som.required' => 'Le numéro SOM est requis',
            'telephone.required' => 'Le téléphone est requis',
            'specialite.required' => 'La spécialité est requise',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        
        // Use user email if not provided
        if (empty($data['email'])) {
            $data['email'] = $request->user()->email;
        }
        
        // Set defaults for boolean fields if not provided
        $data['exactitude_info'] = $data['exactitude_info'] ?? true;
        $data['acceptation_termes'] = $data['acceptation_termes'] ?? true;
        
        // Remove fields that don't exist in schema
        unset($data['a_demande_avancement'], $data['a_dossier_en_cours']);
        
        $data['is_complete'] = true;

        $profile = $candidature->profile()->updateOrCreate(
            ['candidature_id' => $candidature->id],
            $data
        );

        // Update candidature step if needed
        if ($candidature->current_step < 2) {
            $candidature->update(['current_step' => 2]);
        }

        return response()->json([
            'message' => 'Profil enregistré avec succès',
            'profile' => $profile->fresh(),
        ]);
    }

    /**
     * Auto-save profile (partial save)
     */
    public function autosave(Request $request): JsonResponse
    {
        $candidature = $this->getOrCreateCandidature($request);
        
        if (!$candidature->canBeEdited()) {
            return response()->json([
                'message' => 'La candidature est verrouillée',
                'saved' => false
            ], 422);
        }

        $data = $request->only([
            'nom', 'prenom', 'nom_ar', 'prenom_ar', 'cin', 'email', 'date_naissance', 'etablissement',
            'ville', 'departement', 'grade_actuel', 'date_recrutement_es',
            'date_recrutement_fp', 'numero_som', 'telephone', 'specialite', 'date_soutenance_habilitation',
        ]);

        // Filter out null, empty strings, and undefined values
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '' && $value !== 'undefined';
        });

        if (empty($data)) {
            // Return current profile if exists, or empty response
            $profile = $candidature->profile;
            return response()->json([
                'message' => 'Aucune donnée à sauvegarder',
                'profile' => $profile,
                'saved' => false
            ]);
        }

        // Basic validation for autosave (less strict than full save)
        $validator = Validator::make($data, [
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'nom_ar' => 'sometimes|string|max:255',
            'prenom_ar' => 'sometimes|string|max:255',
            'cin' => 'sometimes|string|max:50',
            'email' => 'sometimes|email|max:255',
            'date_naissance' => 'sometimes|date|before:today',
            'etablissement' => 'sometimes|string|max:255',
            'ville' => 'sometimes|string|max:255',
            'departement' => 'sometimes|string|max:255',
            'grade_actuel' => 'sometimes|string|max:255',
            'date_recrutement_es' => 'sometimes|date|before_or_equal:today',
            'date_recrutement_fp' => 'nullable|date|before_or_equal:today',
            'numero_som' => 'nullable|string|max:50',
            'telephone' => 'sometimes|string|max:20',
            'specialite' => 'sometimes|string|max:255',
            'date_soutenance_habilitation' => 'nullable|date|before_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides',
                'errors' => $validator->errors(),
                'saved' => false
            ], 422);
        }

        try {
            $validatedData = $validator->validated();
            
            // Get existing profile
            $profile = $candidature->profile;
            
            if ($profile) {
                // Update existing profile with partial data
                $profile->update($validatedData);
                $profile->refresh();
            } else {
                // For new profiles in autosave, we need all required fields
                // Use defaults for missing fields
                $user = $request->user();
                $createData = array_merge([
                    'nom' => $validatedData['nom'] ?? '',
                    'prenom' => $validatedData['prenom'] ?? '',
                    'nom_ar' => $validatedData['nom_ar'] ?? '',
                    'prenom_ar' => $validatedData['prenom_ar'] ?? '',
                    'cin' => $validatedData['cin'] ?? '',
                    'email' => $validatedData['email'] ?? $user->email,
                    'date_naissance' => $validatedData['date_naissance'] ?? '1970-01-01',
                    'etablissement' => $validatedData['etablissement'] ?? '',
                    'ville' => $validatedData['ville'] ?? '',
                    'departement' => $validatedData['departement'] ?? '',
                    'grade_actuel' => $validatedData['grade_actuel'] ?? '',
                    'date_recrutement_es' => $validatedData['date_recrutement_es'] ?? now()->toDateString(),
                    'date_recrutement_fp' => $validatedData['date_recrutement_fp'] ?? null,
                    'numero_som' => $validatedData['numero_som'] ?? '',
                    'telephone' => $validatedData['telephone'] ?? '',
                    'specialite' => $validatedData['specialite'] ?? '',
                    'date_soutenance_habilitation' => $validatedData['date_soutenance_habilitation'] ?? null,
                    'exactitude_info' => false,
                    'acceptation_termes' => false,
                    'is_complete' => false,
                ], $validatedData);
                
                $profile = $candidature->profile()->create($createData);
            }

            return response()->json([
                'message' => 'Profil sauvegardé automatiquement',
                'profile' => $profile,
            ]);
        } catch (\Exception $e) {
            \Log::error('Autosave error: ' . $e->getMessage(), [
                'candidature_id' => $candidature->id,
                'data' => $data,
                'validated_data' => $validator->validated() ?? [],
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la sauvegarde',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
                'saved' => false
            ], 500);
        }
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
