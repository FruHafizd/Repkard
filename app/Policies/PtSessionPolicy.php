<?php

namespace App\Policies;

use App\Models\PtSession;
use App\Models\User;

class PtSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:PtSession');
    }

    /** Tanpa izin "lihat semua", hanya sesi milik trainer itu sendiri. */
    public function view(User $user, PtSession $ptSession): bool
    {
        return $this->canAccess($user, $ptSession, 'View:PtSession');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:PtSession');
    }

    /** Dipakai untuk menandai selesai / tidak datang / batal. */
    public function update(User $user, PtSession $ptSession): bool
    {
        return $this->canAccess($user, $ptSession, 'Update:PtSession');
    }

    public function delete(User $user, PtSession $ptSession): bool
    {
        return false;
    }

    private function canAccess(User $user, PtSession $session, string $permission): bool
    {
        return $user->can($permission)
            && ($user->can('ViewAll:PtSession') || $session->trainer_id === $user->getKey());
    }
}
