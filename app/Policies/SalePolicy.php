<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Sale');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->can('ViewAll:Sale')
            || ($user->can('View:Sale') && $sale->recorded_by === $user->getKey());
    }

    // Penjualan hanya lahir dari halaman Kasir; tidak diubah atau dihapus, hanya dibatalkan.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Sale $sale): bool
    {
        return false;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return false;
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $user->can('Cancel:Sale')
            && $user->hasRole(UserRole::Owner->value)
            && ! $sale->isCancelled();
    }
}
