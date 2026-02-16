<?php

namespace App\Http\Controllers\President;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureResult;
use App\Services\CommissionAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PresidentResultController extends Controller
{
    public function __construct(private readonly CommissionAccessService $access)
    {
    }

    public function show(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();
        $commission = $this->access->resolveCommissionForUser($user, $request->query('specialite'));

        $candidature->load(['profile']);
        if (!$commission || !$candidature->profile || $candidature->profile->specialite !== $commission->specialite) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $row = CandidatureResult::query()->where('candidature_id', $candidature->id)->first();

        return response()->json([
            'data' => [
                'audition_score' => $row?->audition_score !== null ? (float) $row->audition_score : null,
                'final_score' => $row?->final_score !== null ? (float) $row->final_score : null,
                'pv_text' => $row?->pv_text,
                'validated_at' => optional($row?->validated_at)?->toISOString(),
            ],
        ]);
    }

    public function upsert(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();
        $commission = $this->access->resolveCommissionForUser($user, $request->query('specialite'));

        $candidature->load(['profile']);
        if (!$commission || !$candidature->profile || $candidature->profile->specialite !== $commission->specialite) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'audition_score' => 'nullable|numeric|min:0|max:100',
            'final_score' => 'nullable|numeric|min:0|max:100',
            'pv_text' => 'nullable|string|max:200000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        DB::transaction(function () use ($candidature, $data) {
            CandidatureResult::query()->updateOrCreate(
                ['candidature_id' => $candidature->id],
                [
                    'audition_score' => $data['audition_score'] ?? null,
                    'final_score' => $data['final_score'] ?? null,
                    'pv_text' => $data['pv_text'] ?? null,
                ]
            );
        });

        return response()->json(['message' => 'Résultat enregistré']);
    }

    public function validateResult(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();
        $commission = $this->access->resolveCommissionForUser($user, $request->query('specialite'));

        $candidature->load(['profile']);
        if (!$commission || !$candidature->profile || $candidature->profile->specialite !== $commission->specialite) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$this->access->isPresidentForCommission($user, $commission)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $row = CandidatureResult::query()->firstOrCreate(['candidature_id' => $candidature->id]);
        $row->update([
            'validated_at' => now(),
            'validated_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Validation finale effectuée',
            'data' => [
                'validated_at' => optional($row->validated_at)?->toISOString(),
            ],
        ]);
    }
}
