<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidatureAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $specialite = trim((string) $request->query('specialite', ''));
        $etablissement = trim((string) $request->query('etablissement', ''));
        $status = trim((string) $request->query('status', ''));

        $query = Candidature::query()
            ->with(['user:id,name,email', 'profile:id,candidature_id,etablissement,specialite']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($specialite !== '') {
            $query->whereHas('profile', function ($q) use ($specialite) {
                $q->where('specialite', 'like', '%' . $specialite . '%');
            });
        }

        if ($etablissement !== '') {
            $query->whereHas('profile', function ($q) use ($etablissement) {
                $q->where('etablissement', 'like', '%' . $etablissement . '%');
            });
        }

        $candidatures = $query
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get()
            ->map(function (Candidature $c) {
                return [
                    'id' => $c->id,
                    'status' => $c->status,
                    'current_step' => $c->current_step,
                    'progress' => $c->progress,
                    'submitted_at' => optional($c->submitted_at)?->toISOString(),
                    'locked_at' => optional($c->locked_at)?->toISOString(),
                    'created_at' => optional($c->created_at)?->toISOString(),
                    'updated_at' => optional($c->updated_at)?->toISOString(),
                    'candidate' => [
                        'id' => $c->user?->id,
                        'name' => $c->user?->name,
                        'email' => $c->user?->email,
                    ],
                    'profile' => $c->profile ? [
                        'specialite' => $c->profile->specialite,
                        'etablissement' => $c->profile->etablissement,
                    ] : null,
                ];
            });

        return response()->json(['data' => $candidatures]);
    }

    public function export(Request $request): StreamedResponse
    {
        $specialite = trim((string) $request->query('specialite', ''));
        $etablissement = trim((string) $request->query('etablissement', ''));
        $status = trim((string) $request->query('status', ''));

        $query = Candidature::query()
            ->with(['user:id,name,email', 'profile:id,candidature_id,etablissement,specialite']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($specialite !== '') {
            $query->whereHas('profile', function ($q) use ($specialite) {
                $q->where('specialite', 'like', '%' . $specialite . '%');
            });
        }

        if ($etablissement !== '') {
            $query->whereHas('profile', function ($q) use ($etablissement) {
                $q->where('etablissement', 'like', '%' . $etablissement . '%');
            });
        }

        $filename = 'candidatures_export_' . now()->format('Ymd_His') . '.csv';

        $response = new StreamedResponse(function () use ($query) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'ID',
                'Candidat',
                'Email',
                'Statut',
                'Étape courante',
                'Progression (%)',
                'Spécialité',
                'Établissement',
                'Soumise le',
                'Verrouillée le',
                'Mise à jour le',
            ], ';');

            $query
                ->orderByDesc('updated_at')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $c) {
                        $progress = $c->progress;
                        $percent = is_array($progress) ? ($progress['percent'] ?? null) : null;

                        fputcsv($out, [
                            $c->id,
                            $c->user?->name,
                            $c->user?->email,
                            $c->status,
                            $c->current_step,
                            $percent,
                            $c->profile?->specialite,
                            $c->profile?->etablissement,
                            optional($c->submitted_at)?->toISOString(),
                            optional($c->locked_at)?->toISOString(),
                            optional($c->updated_at)?->toISOString(),
                        ], ';');
                    }
                });

            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
