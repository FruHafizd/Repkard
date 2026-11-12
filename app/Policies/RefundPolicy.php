<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Refund');
    }

    public function view(User $user, Refund $refund): bool
    {
        return $user->can('View:Refund');
    }

    // Refund hanya lahir dari aksi Refund di member, dan tidak pernah diubah atau dihapus.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Refund $refund): bool
    {
        return false;
    }

    public function delete(User $user, Refund $refund): bool
    {
        return false;
    }
}
