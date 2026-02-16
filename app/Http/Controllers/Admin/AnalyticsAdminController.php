<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AnalyticsAdminController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'days' => 'nullable|integer|min:7|max:90',
            'recent_limit' => 'nullable|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $days = (int) ($request->query('days', 14));
        $recentLimit = (int) ($request->query('recent_limit', 6));

        $start = Carbon::now()->startOfDay()->subDays($days - 1);
        $end = Carbon::now()->endOfDay();

        $totals = [
            'dossiers_total' => Candidature::count(),
            'dossiers_submitted' => Candidature::where('status', Candidature::STATUS_SUBMITTED)->count(),
            'candidats_total' => User::where('role', User::ROLE_CANDIDAT)->count(),
        ];

        // Per-day created dossiers
        $createdByDay = Candidature::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd')
            ->toArray();

        // Per-day submitted dossiers
        $submittedByDay = Candidature::query()
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$start, $end])
            ->selectRaw('DATE(submitted_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd')
            ->toArray();

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => $day,
                'dossiers_created' => (int) ($createdByDay[$day] ?? 0),
                'dossiers_submitted' => (int) ($submittedByDay[$day] ?? 0),
            ];
        }

        $recentCandidates = User::query()
            ->where('role', User::ROLE_CANDIDAT)
            ->orderByDesc('created_at')
            ->limit($recentLimit)
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'created_at' => optional($u->created_at)?->toISOString(),
                ];
            })
            ->values();

        // Breakdown by status
        $byStatus = Candidature::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        return response()->json([
            'data' => [
                'totals' => $totals,
                'series' => $series,
                'recent_candidates' => $recentCandidates,
                'by_status' => $byStatus,
            ],
        ]);
    }
}
