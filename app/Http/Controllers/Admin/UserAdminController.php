<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 10);

        if ($perPage < 1) {
            $perPage = 10;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = User::query();

        if ($role !== '') {
            $query->where('role', $role);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            });
        }

        // Put current user first (only on first page)
        if ($page <= 1 && $request->user()) {
            $query->orderByRaw('id = ? desc', [$request->user()->id]);
        }

        $users = $query
            ->orderByDesc('created_at')
            ->paginate(
                $perPage,
                ['id', 'name', 'email', 'role', 'created_at', 'updated_at'],
                'page',
                $page
            );

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|unique:users,email',
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'date_naissance' => 'required|date',
            'etablissement' => 'required|string|max:255',
            'ville' => 'required|string|max:255',
            'departement' => 'required|string|max:255',
            'grade_actuel' => 'required|string|max:255',
            'date_recrutement_es' => 'required|date',
            'date_recrutement_fp' => 'nullable|date',
            'numero_som' => 'required|string|max:255',
            'telephone' => 'required|string|max:50',
            'specialite' => 'required|string|max:255',
        ], [
            'email.required' => 'L\'email est requis',
            'email.unique' => 'Cet email existe déjà',
            'nom.required' => 'Le nom est requis',
            'prenom.required' => 'Le prénom est requis',
            'date_naissance.required' => 'La date de naissance est requise',
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
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $created = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => trim($data['prenom'] . ' ' . $data['nom']),
                'email' => $data['email'],
                'password' => Hash::make(Str::random(24)),
                'role' => User::ROLE_CANDIDAT,
            ]);

            $candidature = Candidature::create([
                'user_id' => $user->id,
                'current_step' => 1,
                'status' => Candidature::STATUS_DRAFT,
            ]);

            CandidatureProfile::create([
                'candidature_id' => $candidature->id,
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'],
                'date_naissance' => $data['date_naissance'],
                'etablissement' => $data['etablissement'],
                'ville' => $data['ville'],
                'departement' => $data['departement'],
                'grade_actuel' => $data['grade_actuel'],
                'date_recrutement_es' => $data['date_recrutement_es'],
                'date_recrutement_fp' => $data['date_recrutement_fp'] ?? null,
                'numero_som' => $data['numero_som'],
                'telephone' => $data['telephone'],
                'specialite' => $data['specialite'],
                // Created by admin
                'exactitude_info' => true,
                'acceptation_termes' => true,
                'is_complete' => true,
            ]);

            return $user;
        });

        return response()->json([
            'message' => 'Utilisateur créé',
            'data' => $created->only(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|in:' . implode(',', [
                User::ROLE_CANDIDAT,
                User::ROLE_SYSTEME,
                User::ROLE_ADMIN,
                User::ROLE_COMMISSION,
                User::ROLE_PRESIDENT,
            ]),
        ], [
            'role.in' => 'Rôle invalide',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $authUser = $request->user();

        if ($authUser && $authUser->id === $user->id && array_key_exists('role', $validated)) {
            return response()->json([
                'errors' => [
                    'role' => ['Vous ne pouvez pas modifier votre propre rôle'],
                ],
            ], 422);
        }

        $user->update($validated);
        $user->refresh();

        return response()->json([
            'message' => 'Utilisateur modifié',
            'data' => $user->only(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser && $authUser->id === $user->id) {
            return response()->json([
                'errors' => [
                    'user' => ['Vous ne pouvez pas supprimer votre propre compte'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $user->delete();
        });

        return response()->json([
            'message' => 'Utilisateur supprimé',
        ]);
    }
}
