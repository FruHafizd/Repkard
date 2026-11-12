<?php

namespace App\Policies;

use App\Models\PromoCode;
use App\Models\User;

class PromoCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:PromoCode');
    }

    public function view(User $user, PromoCode $promoCode): bool
    {
        return $user->can('View:PromoCode');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:PromoCode');
    }

    public function update(User $user, PromoCode $promoCode): bool
    {
        return $user->can('Update:PromoCode');
    }

    // Kode yang sudah pernah dipakai tidak boleh dihapus (riwayat pembayaran); cukup dinonaktifkan.
    public function delete(User $user, PromoCode $promoCode): bool
    {
        return $user->can('Delete:PromoCode') && ! $promoCode->payments()->exists();
    }
}
