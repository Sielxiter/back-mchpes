<?php

namespace App\Http\Controllers\Candidat;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\Deadline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidatureController extends Controller
{
    /**
     * Get current user's candidature or create a new one
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $candidature = $user->candidature()->with([
            'profile',
            'enseignements',
            'pfes',
            'activites.document',
            'documents',
        ])->first();

        // Create new candidature if doesn't exist
        if (!$candidature) {
            $candidature = Candidature::create([
                'user_id' => $user->id,
                'current_step' => 1,
                'status' => Candidature::STATUS_DRAFT,
            ]);
            $candidature->load(['profile', 'enseignements', 'pfes', 'activites', 'documents']);
        }

        // Get relevant deadline
        $deadline = Deadline::where('stage', 'candidature')
            ->where('due_at', '>', now())
            ->orderBy('due_at')
            ->first();

        return response()->json([
            'candidature' => $candidature,
            'progress' => $candidature->progress,
            'deadline' => $deadline,
            'is_locked' => $candidature->isLocked(),
            'can_edit' => $candidature->canBeEdited(),
        ]);
    }

    /**
     * Get candidature status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $candidature = $user->candidature;

        if (!$candidature) {
            return response()->json([
                'exists' => false,
                'step' => 1,
                'status' => 'draft',
            ]);
        }

        return response()->json([
            'exists' => true,
            'step' => $candidature->current_step,
            'status' => $candidature->status,
            'progress' => $candidature->progress,
            'is_locked' => $candidature->isLocked(),
            'submitted_at' => $candidature->submitted_at,
        ]);
    }

    /**
     * Submit candidature for review
     */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();
        $candidature = $user->candidature;

        if (!$candidature) {
            return response()->json(['error' => 'Aucune candidature trouvée'], 404);
        }

        if ($candidature->isLocked()) {
            return response()->json(['error' => 'La candidature est déjà verrouillée'], 422);
        }

        // Check deadline
        $deadline = Deadline::where('stage', 'candidature')
            ->where('due_at', '>', now())
            ->first();

        if (!$deadline) {
            return response()->json(['error' => 'La date limite de soumission est dépassée'], 422);
        }

        // Validate completeness
        $progress = $candidature->progress;
        if ($progress['percent'] < 100) {
            return response()->json([
                'error' => 'Le dossier est incomplet',
                'progress' => $progress,
            ], 422);
        }

        // Check all activities have required documents
        $activitiesWithoutDocs = $candidature->activites()
            ->where('count', '>', 0)
            ->whereDoesntHave('documents')
            ->count();

        if ($activitiesWithoutDocs > 0) {
            return response()->json([
                'error' => 'Certaines activités n\'ont pas de justificatifs',
                'missing_documents' => $activitiesWithoutDocs,
            ], 422);
        }

        // Submit
        $candidature->update([
            'status' => Candidature::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'locked_at' => now(),
        ]);

        // TODO: Send confirmation email
        // TODO: Send WhatsApp notification

        return response()->json([
            'message' => 'Candidature soumise avec succès',
            'candidature' => $candidature->fresh(),
        ]);
    }
}
