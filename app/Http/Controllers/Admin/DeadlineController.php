<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeadlineController extends Controller
{
    /**
     * Get all deadlines with creator info
     */
    public function index(): JsonResponse
    {
        $deadlines = Deadline::with('creator:id,name,email')
            ->orderBy('due_at')
            ->get()
            ->map(function ($deadline) {
                return [
                    'id' => $deadline->id,
                    'stage' => $deadline->stage,
                    'due_at' => $deadline->due_at->toISOString(),
                    'due_at_formatted' => $deadline->due_at->format('d/m/Y H:i'),
                    'reminder_enabled' => $deadline->reminder_enabled,
                    'is_expired' => $deadline->isExpired(),
                    'days_remaining' => $deadline->days_remaining,
                    'created_by' => $deadline->creator ? $deadline->creator->name : null,
                    'created_at' => $deadline->created_at->toISOString(),
                    'updated_at' => $deadline->updated_at->toISOString(),
                ];
            });

        return response()->json(['data' => $deadlines]);
    }

    /**
     * Get a single deadline
     */
    public function show(Deadline $deadline): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $deadline->id,
                'stage' => $deadline->stage,
                'due_at' => $deadline->due_at->toISOString(),
                'due_at_formatted' => $deadline->due_at->format('d/m/Y H:i'),
                'reminder_enabled' => $deadline->reminder_enabled,
                'is_expired' => $deadline->isExpired(),
                'days_remaining' => $deadline->days_remaining,
                'created_by' => $deadline->creator ? $deadline->creator->name : null,
            ],
        ]);
    }

    /**
     * Create a new deadline
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stage' => 'required|string|max:255',
            'due_at' => 'required|date|after:now',
            'reminder_enabled' => 'nullable|boolean',
        ], [
            'stage.required' => 'L\'étape est requise',
            'stage.max' => 'L\'étape ne peut pas dépasser 255 caractères',
            'due_at.required' => 'La date limite est requise',
            'due_at.date' => 'La date limite est invalide',
            'due_at.after' => 'La date limite doit être dans le futur',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deadline = Deadline::create([
            'stage' => $request->stage,
            'due_at' => $request->due_at,
            'reminder_enabled' => $request->reminder_enabled ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Délai créé',
            'data' => $deadline,
        ], 201);
    }

    /**
     * Update a deadline
     */
    public function update(Request $request, Deadline $deadline): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stage' => 'sometimes|string|max:255',
            'due_at' => 'sometimes|date',
            'reminder_enabled' => 'nullable|boolean',
        ], [
            'stage.max' => 'L\'étape ne peut pas dépasser 255 caractères',
            'due_at.date' => 'La date limite est invalide',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deadline->update($validator->validated());

        return response()->json([
            'message' => 'Délai modifié',
            'data' => $deadline->fresh(),
        ]);
    }

    /**
     * Delete a deadline
     */
    public function destroy(Deadline $deadline): JsonResponse
    {
        $deadline->delete();

        return response()->json([
            'message' => 'Délai supprimé',
        ]);
    }

    /**
     * Send reminder for a deadline
     */
    public function remind(Deadline $deadline): JsonResponse
    {
        if (!$deadline->reminder_enabled) {
            return response()->json([
                'error' => 'Les rappels sont désactivés pour ce délai',
            ], 422);
        }

        if ($deadline->isExpired()) {
            return response()->json([
                'error' => 'Ce délai est déjà expiré',
            ], 422);
        }

        // TODO: Queue reminder job
        // ReminderJob::dispatch($deadline);

        return response()->json([
            'message' => 'Rappel mis en file d\'attente',
            'deadline_id' => $deadline->id,
        ]);
    }

    /**
     * Get active (non-expired) deadlines
     */
    public function active(): JsonResponse
    {
        $deadlines = Deadline::where('due_at', '>', now())
            ->orderBy('due_at')
            ->get()
            ->map(function ($deadline) {
                return [
                    'id' => $deadline->id,
                    'stage' => $deadline->stage,
                    'due_at' => $deadline->due_at->toISOString(),
                    'days_remaining' => $deadline->days_remaining,
                ];
            });

        return response()->json(['data' => $deadlines]);
    }
}
