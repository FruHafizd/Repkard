<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        // Member boleh membuka daftar; isinya dibatasi ke miliknya sendiri di resource panel member.
        return $user->hasRole(UserRole::Member->value) || $user->can('ViewAny:Payment');
    }

    public function view(User $user, Payment $payment): bool
    {
        // Member hanya pembayarannya sendiri (aturan keras, tidak bisa diubah lewat Role).
        if ($payment->member?->user_id === $user->getKey()) {
            return true;
        }

        if ($user->can('ViewAll:Payment')) {
            return true;
        }

        return $user->can('View:Payment') && $payment->recorded_by === $user->getKey();
    }

    // Pembayaran hanya lahir dari aktivasi / perpanjangan, dan tidak pernah diubah atau dihapus.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Pembatalan hanya Owner (aturan keras).
     */
    public function cancel(User $user, Payment $payment): bool
    {
        return $user->hasRole(UserRole::Owner->value) && ! $payment->isCancelled();
    }
}
