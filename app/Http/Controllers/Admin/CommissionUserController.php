<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionUser;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommissionUserController extends Controller
{
    public function indexForCommission(Commission $commission): JsonResponse
    {
        $rows = CommissionUser::query()
            ->where('commission_id', $commission->id)
            ->with('user:id,name,email,role')
            ->get()
            ->sortByDesc('is_president')
            ->values()
            ->map(function (CommissionUser $cu) {
                return [
                    'id' => $cu->id,
                    'user' => [
                        'id' => $cu->user?->id,
                        'name' => $cu->user?->name,
                        'email' => $cu->user?->email,
                        'role' => $cu->user?->role,
                    ],
                    'is_president' => (bool) $cu->is_president,
                    'created_at' => optional($cu->created_at)?->toISOString(),
                ];
            });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'commission' => [
                    'id' => $commission->id,
                    'specialite' => $commission->specialite,
                ],
                'count' => $rows->count(),
            ],
        ]);
    }

    public function showForUser(Request $request, User $user): JsonResponse
    {
        $assignments = CommissionUser::query()
            ->where('user_id', $user->id)
            ->with('commission:id,specialite')
            ->get()
            ->map(function (CommissionUser $cu) {
                return [
                    'id' => $cu->id,
                    'commission' => [
                        'id' => $cu->commission?->id,
                        'specialite' => $cu->commission?->specialite,
                    ],
                    'is_president' => (bool) $cu->is_president,
                    'created_at' => optional($cu->created_at)?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'assignments' => $assignments,
            ],
        ]);
    }

    public function assignForUser(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialite' => 'required|string|max:255',
            'is_president' => 'required|boolean',
        ], [
            'specialite.required' => 'La spécialité est requise',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $specialiteName = trim((string) $request->input('specialite'));
        $isPresident = (bool) $request->boolean('is_president');

        $result = DB::transaction(function () use ($request, $user, $specialiteName, $isPresident) {
            // Ensure specialite exists (so UI can select from DB)
            Specialite::query()->firstOrCreate(['name' => $specialiteName], ['name' => $specialiteName]);

            // Ensure commission exists
            $commission = Commission::query()->firstOrCreate(
                ['specialite' => $specialiteName],
                ['created_by' => $request->user()?->id]
            );

            // Enforce max 5 members (4 + 1 president)
            $count = CommissionUser::query()->where('commission_id', $commission->id)->count();
            $existing = CommissionUser::query()
                ->where('commission_id', $commission->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$existing && $count >= 5) {
                return response()->json([
                    'error' => 'La commission est déjà composée de 5 membres',
                ], 422);
            }

            if ($isPresident) {
                // Only one president
                CommissionUser::query()->where('commission_id', $commission->id)->update(['is_president' => false]);
            }

            $row = CommissionUser::query()->updateOrCreate(
                ['commission_id' => $commission->id, 'user_id' => $user->id],
                ['is_president' => $isPresident]
            );

            return response()->json([
                'message' => 'Affectation enregistrée',
                'data' => [
                    'id' => $row->id,
                    'commission' => [
                        'id' => $commission->id,
                        'specialite' => $commission->specialite,
                    ],
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'is_president' => (bool) $row->is_president,
                ],
            ], 201);
        });

        return $result;
    }

    public function removeForUser(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'specialite' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $specialiteName = trim((string) $request->input('specialite'));
        $commission = Commission::query()->where('specialite', $specialiteName)->first();

        if (!$commission) {
            return response()->json(['message' => 'Aucune commission'], 200);
        }

        CommissionUser::query()
            ->where('commission_id', $commission->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['message' => 'Affectation supprimée']);
    }
}
