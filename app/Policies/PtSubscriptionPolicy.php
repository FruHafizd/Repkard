<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PtSubscription;
use App\Models\User;

class PtSubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:PtSubscription');
    }

    public function view(User $user, PtSubscription $ptSubscription): bool
    {
        return $user->can('ViewAll:PtSubscription')
            || ($user->can('View:PtSubscription') && $ptSubscription->trainer_id === $user->getKey());
    }

    // Paket PT hanya lahir dari aksi "Jual paket PT" di member.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PtSubscription $ptSubscription): bool
    {
        return false;
    }

    public function delete(User $user, PtSubscription $ptSubscription): bool
    {
        return false;
    }

    /** Ganti trainer dan batalkan: hanya Owner (aturan keras). */
    public function manage(User $user, PtSubscription $ptSubscription): bool
    {
        return $user->hasRole(UserRole::Owner->value) && ! $ptSubscription->isCancelled();
    }
}
