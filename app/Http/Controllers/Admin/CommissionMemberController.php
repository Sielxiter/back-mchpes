<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommissionMemberController extends Controller
{
    public function store(Request $request, Commission $commission): JsonResponse
    {
        if ($commission->members()->count() >= 5) {
            return response()->json([
                'error' => 'La commission est déjà composée de 5 membres',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'etablissement' => 'required|string|max:255',
            'universite' => 'required|string|max:255',
            'grade' => 'required|string|max:255',
            'specialite' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telephone' => 'required|string|max:50',
            'is_president' => 'nullable|boolean',
        ], [
            'nom.required' => 'Le nom est requis',
            'prenom.required' => 'Le prénom est requis',
            'etablissement.required' => 'L\'établissement est requis',
            'universite.required' => 'L\'université est requise',
            'grade.required' => 'Le grade est requis',
            'specialite.required' => 'La spécialité est requise',
            'email.required' => 'L\'email est requis',
            'email.email' => 'L\'email est invalide',
            'telephone.required' => 'Le téléphone est requis',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $makePresident = (bool)($data['is_president'] ?? false);

        $member = DB::transaction(function () use ($commission, $data, $makePresident) {
            if ($makePresident) {
                $commission->members()->update(['is_president' => false]);
            }

            return $commission->members()->create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'etablissement' => $data['etablissement'],
                'universite' => $data['universite'],
                'grade' => $data['grade'],
                'specialite' => $data['specialite'],
                'email' => $data['email'],
                'telephone' => $data['telephone'],
                'is_president' => $makePresident,
            ]);
        });

        return response()->json([
            'message' => 'Membre ajouté',
            'data' => $member,
        ], 201);
    }

    public function setPresident(Request $request, Commission $commission, CommissionMember $member): JsonResponse
    {
        if ($member->commission_id !== $commission->id) {
            return response()->json(['error' => 'Membre invalide'], 404);
        }

        DB::transaction(function () use ($commission, $member) {
            $commission->members()->update(['is_president' => false]);
            $member->update(['is_president' => true]);
        });

        return response()->json([
            'message' => 'Président désigné',
            'data' => $member->fresh(),
        ]);
    }

    public function destroy(Commission $commission, CommissionMember $member): JsonResponse
    {
        if ($member->commission_id !== $commission->id) {
            return response()->json(['error' => 'Membre invalide'], 404);
        }

        $member->delete();

        return response()->json(['message' => 'Membre supprimé']);
    }
}
