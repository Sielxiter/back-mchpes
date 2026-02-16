<?php

namespace App\Http\Controllers\Commission;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\CandidatureDocument;
use App\Services\CommissionAccessService;
use App\Services\SecureFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CommissionDossierController extends Controller
{
    public function __construct(
        private readonly CommissionAccessService $access,
        private readonly SecureFileUploadService $files,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $commission = $this->access->resolveCommissionForUser($user, $request->query('specialite'));

        if (!$commission) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'commission' => null,
                ],
            ]);
        }

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 12);
        if ($perPage < 1) $perPage = 12;
        if ($perPage > 100) $perPage = 100;

        $query = Candidature::query()
            ->with(['user:id,name,email,role', 'profile'])
            ->whereHas('profile', function ($q) use ($commission) {
                $q->where('specialite', $commission->specialite);
            })
            ->orderByDesc('updated_at');

        $cands = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($cands->items())->map(function (Candidature $c) {
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
                ] : null,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $cands->currentPage(),
                'per_page' => $cands->perPage(),
                'total' => $cands->total(),
                'last_page' => $cands->lastPage(),
                'commission' => [
                    'id' => $commission->id,
                    'specialite' => $commission->specialite,
                ],
            ],
        ]);
    }

    public function show(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();
        $commission = $this->access->resolveCommissionForUser($user, $request->query('specialite'));

        $candidature->load(['user:id,name,email,role', 'profile']);

        if (!$commission || !$candidature->profile || $candidature->profile->specialite !== $commission->specialite) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

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
        $user = $request->user();
        $commission = $this->access->resolveCommissionForUser($user, $request->query('specialite'));

        $candidature->load(['profile']);

        if (!$commission || !$candidature->profile || $candidature->profile->specialite !== $commission->specialite) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) $perPage = 20;
        if ($perPage > 100) $perPage = 100;

        $query = $candidature->documents()
            ->with('activite')
            ->where('mime_type', 'application/pdf')
            ->orderByDesc('created_at');

        $docs = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($docs->items())->map(function (CandidatureDocument $doc) {
            return [
                'id' => $doc->id,
                'type' => $doc->type,
                'category' => 'PDF',
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

    public function downloadDocument(Request $request, CandidatureDocument $document): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $commission = $this->access->resolveCommissionForUser($user, $request->query('specialite'));

        $document->load(['candidature.profile']);

        $profile = $document->candidature?->profile;
        if (!$commission || !$profile || $profile->specialite !== $commission->specialite) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $info = $this->files->getFileInfoForDownload($document);
            return response()->download($info['full_path'], $info['original_name'], [
                'Content-Type' => $info['mime_type'] ?? 'application/octet-stream',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
