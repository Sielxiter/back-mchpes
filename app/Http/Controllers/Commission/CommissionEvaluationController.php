<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureEvaluation;
use App\Services\CommissionAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommissionEvaluationController extends Controller
{
    public function __construct(private readonly CommissionAccessService $access)
    {
    }

    public function index(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();
        $commission = $this->access->resolveCommissionForUser($user, $request->query('specialite'));

        $candidature->load(['profile']);
        if (!$commission || !$candidature->profile || $candidature->profile->specialite !== $commission->specialite) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $notes = CandidatureEvaluation::query()
            ->where('candidature_id', $candidature->id)
            ->where('user_id', $user->id)
            ->orderBy('criterion')
            ->get(['criterion', 'score', 'comment', 'updated_at'])
            ->map(fn ($n) => [
                'criterion' => $n->criterion,
                'score' => $n->score !== null ? (float) $n->score : null,
                'comment' => $n->comment,
                'updated_at' => optional($n->updated_at)?->toISOString(),
            ])
            ->values();

        return response()->json(['data' => $notes]);
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
            'items' => 'required|array|min:1',
            'items.*.criterion' => 'required|string|max:255',
            'items.*.score' => 'nullable|numeric|min:0|max:100',
            'items.*.comment' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $items = $validator->validated()['items'];

        DB::transaction(function () use ($items, $candidature, $user) {
            foreach ($items as $it) {
                CandidatureEvaluation::query()->updateOrCreate(
                    [
                        'candidature_id' => $candidature->id,
                        'user_id' => $user->id,
                        'criterion' => $it['criterion'],
                    ],
                    [
                        'score' => $it['score'] ?? null,
                        'comment' => $it['comment'] ?? null,
                    ]
                );
            }
        });

        return response()->json(['message' => 'Notes enregistrées']);
    }
}
