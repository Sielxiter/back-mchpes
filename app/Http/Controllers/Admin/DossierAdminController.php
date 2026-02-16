<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DossierAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $specialite = trim((string) $request->query('specialite', ''));
        $etablissement = trim((string) $request->query('etablissement', ''));
        $status = trim((string) $request->query('status', ''));
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 12);

        if ($perPage < 1) {
            $perPage = 12;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = Candidature::query()
            ->with(['user:id,name,email,role', 'profile']);

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
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($candidatures->items())->map(function (Candidature $c) {
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
                    'role' => $c->user?->role,
                ],
                'profile' => $c->profile ? [
                    'specialite' => $c->profile->specialite,
                    'etablissement' => $c->profile->etablissement,
                    'nom' => $c->profile->nom,
                    'prenom' => $c->profile->prenom,
                    'date_naissance' => $c->profile->date_naissance,
                    'numero_som' => $c->profile->numero_som,
                    'telephone' => $c->profile->telephone,
                    'ville' => $c->profile->ville,
                    'departement' => $c->profile->departement,
                    'grade_actuel' => $c->profile->grade_actuel,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $candidatures->currentPage(),
                'per_page' => $candidatures->perPage(),
                'total' => $candidatures->total(),
                'last_page' => $candidatures->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Candidature $candidature): JsonResponse
    {
        $candidature->load(['user:id,name,email,role', 'profile']);

        return response()->json([
            'data' => [
                'id' => $candidature->id,
                'status' => $candidature->status,
                'current_step' => $candidature->current_step,
                'progress' => $candidature->progress,
                'submitted_at' => optional($candidature->submitted_at)?->toISOString(),
                'locked_at' => optional($candidature->locked_at)?->toISOString(),
                'created_at' => optional($candidature->created_at)?->toISOString(),
                'updated_at' => optional($candidature->updated_at)?->toISOString(),
                'candidate' => [
                    'id' => $candidature->user?->id,
                    'name' => $candidature->user?->name,
                    'email' => $candidature->user?->email,
                    'role' => $candidature->user?->role,
                ],
                'profile' => $candidature->profile,
            ],
        ]);
    }

    public function documents(Request $request, Candidature $candidature): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);

        if ($perPage < 1) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = $candidature->documents()
            ->with('activite')
            ->where('mime_type', 'application/pdf')
            ->orderByDesc('created_at');

        $docs = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($docs->items())->map(function (CandidatureDocument $doc) {
            return [
                'id' => $doc->id,
                'type' => $doc->type,
                'category' => $this->categoryForType($doc->type),
                'original_name' => $doc->original_name,
                'mime_type' => $doc->mime_type,
                'size' => $doc->size,
                'is_verified' => $doc->is_verified,
                'created_at' => optional($doc->created_at)?->toISOString(),
                'activite' => $doc->activite ? [
                    'id' => $doc->activite->id,
                    'type' => $doc->activite->type,
                    'category' => $doc->activite->category,
                    'subcategory' => $doc->activite->subcategory,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $docs->currentPage(),
                'per_page' => $docs->perPage(),
                'total' => $docs->total(),
                'last_page' => $docs->lastPage(),
            ],
        ]);
    }

    private function categoryForType(string $type): string
    {
        return match ($type) {
            CandidatureDocument::TYPE_PROFILE_PDF => 'Profil',
            CandidatureDocument::TYPE_ENSEIGNEMENTS_PDF => 'Enseignements',
            CandidatureDocument::TYPE_PFE_PDF => 'PFE',
            CandidatureDocument::TYPE_SIGNED_DOCUMENT => 'Document signé',
            CandidatureDocument::TYPE_ACTIVITE_ATTESTATION => 'Attestations activités',
            default => 'Autres',
        };
    }
}
