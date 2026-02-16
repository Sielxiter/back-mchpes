<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\CommissionUser;
use App\Models\User;

class CommissionAccessService
{
    /**
     * Resolve the commission for a user.
     * If multiple commissions, optionally pick by specialite.
     */
    public function resolveCommissionForUser(User $user, ?string $specialite = null): ?Commission
    {
        $query = CommissionUser::query()
            ->where('user_id', $user->id)
            ->with('commission');

        if ($specialite !== null && trim($specialite) !== '') {
            $spec = trim($specialite);
            $query->whereHas('commission', function ($q) use ($spec) {
                $q->where('specialite', $spec);
            });
        }

        $row = $query->orderByDesc('is_president')->first();

        return $row?->commission;
    }

    public function isPresidentForCommission(User $user, Commission $commission): bool
    {
        return CommissionUser::query()
            ->where('user_id', $user->id)
            ->where('commission_id', $commission->id)
            ->where('is_president', true)
            ->exists();
    }
}
