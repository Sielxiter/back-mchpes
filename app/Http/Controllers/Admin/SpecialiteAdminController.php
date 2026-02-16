<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Specialite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SpecialiteAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $specialites = Specialite::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Specialite $s) => [
                'id' => $s->id,
                'name' => $s->name,
            ]);

        return response()->json(['data' => $specialites]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:specialites,name',
        ], [
            'name.required' => 'La spécialité est requise',
            'name.unique' => 'Cette spécialité existe déjà',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $s = Specialite::create([
            'name' => trim((string) $request->input('name')),
        ]);

        return response()->json([
            'message' => 'Spécialité créée',
            'data' => [
                'id' => $s->id,
                'name' => $s->name,
            ],
        ], 201);
    }
}
