<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WalkIn;

class WalkInPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:WalkIn');
    }

    public function view(User $user, WalkIn $walkIn): bool
    {
        return $user->can('ViewAll:WalkIn')
            || ($user->can('View:WalkIn') && $walkIn->recorded_by === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->can('Create:WalkIn');
    }

    // Catatan uang tidak pernah diubah atau dihapus, hanya dibatalkan.
    public function update(User $user, WalkIn $walkIn): bool
    {
        return false;
    }

    public function delete(User $user, WalkIn $walkIn): bool
    {
        return false;
    }

    /**
     * Pembatalan hanya Owner (aturan keras).
     */
    public function cancel(User $user, WalkIn $walkIn): bool
    {
        return $user->hasRole(UserRole::Owner->value) && ! $walkIn->isCancelled();
    }
}
