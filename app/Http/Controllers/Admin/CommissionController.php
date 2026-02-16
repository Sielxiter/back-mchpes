<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommissionController extends Controller
{
    public function index(): JsonResponse
    {
        $commissions = Commission::withCount('members')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Commission $c) {
                return [
                    'id' => $c->id,
                    'specialite' => $c->specialite,
                    'members_count' => $c->members_count,
                    'created_at' => optional($c->created_at)?->toISOString(),
                ];
            });

        return response()->json(['data' => $commissions]);
    }

    public function show(Commission $commission): JsonResponse
    {
        $commission->load(['members']);

        return response()->json([
            'data' => [
                'id' => $commission->id,
                'specialite' => $commission->specialite,
                'members' => $commission->members
                    ->sortByDesc('is_president')
                    ->values()
                    ->map(function ($m) {
                        return [
                            'id' => $m->id,
                            'nom' => $m->nom,
                            'prenom' => $m->prenom,
                            'etablissement' => $m->etablissement,
                            'universite' => $m->universite,
                            'grade' => $m->grade,
                            'specialite' => $m->specialite,
                            'email' => $m->email,
                            'telephone' => $m->telephone,
                            'is_president' => (bool) $m->is_president,
                            'created_at' => optional($m->created_at)?->toISOString(),
                        ];
                    }),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialite' => 'required|string|max:255',
        ], [
            'specialite.required' => 'La spécialité est requise',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $specialite = trim((string) $request->input('specialite'));
        $commission = Commission::query()->firstOrCreate(
            ['specialite' => $specialite],
            ['created_by' => $request->user()->id]
        );

        return response()->json([
            'message' => 'Commission créée',
            'data' => $commission,
        ], $commission->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Commission $commission): JsonResponse
    {
        $commission->delete();

        return response()->json(['message' => 'Commission supprimée']);
    }
}
