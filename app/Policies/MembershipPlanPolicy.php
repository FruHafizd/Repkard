<?php

namespace App\Policies;

use App\Models\MembershipPlan;
use App\Models\User;

class MembershipPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:MembershipPlan');
    }

    public function view(User $user, MembershipPlan $membershipPlan): bool
    {
        return $user->can('View:MembershipPlan');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:MembershipPlan');
    }

    public function update(User $user, MembershipPlan $membershipPlan): bool
    {
        return $user->can('Update:MembershipPlan');
    }

    // Paket tidak pernah dihapus, hanya dinonaktifkan (riwayat membership tetap utuh).
    public function delete(User $user, MembershipPlan $membershipPlan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, MembershipPlan $membershipPlan): bool
    {
        return false;
    }

    public function forceDelete(User $user, MembershipPlan $membershipPlan): bool
    {
        return false;
    }
}
