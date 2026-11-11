<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CheckIn;
use App\Models\User;

class CheckInPolicy
{
    public function viewAny(User $user): bool
    {
        // Member boleh membuka daftar; isinya dibatasi ke miliknya sendiri di resource panel member.
        return $user->hasRole(UserRole::Member->value) || $user->can('ViewAny:CheckIn');
    }

    public function view(User $user, CheckIn $checkIn): bool
    {
        // Member hanya riwayatnya sendiri (aturan keras).
        if ($checkIn->member?->user_id === $user->getKey()) {
            return true;
        }

        return $user->can('View:CheckIn');
    }

    // Riwayat hanya lahir dari proses scan; pengubahan/penghapusan hanya Owner.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CheckIn $checkIn): bool
    {
        return $user->can('Update:CheckIn');
    }

    public function delete(User $user, CheckIn $checkIn): bool
    {
        return $user->can('Delete:CheckIn');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:CheckIn');
    }
}
