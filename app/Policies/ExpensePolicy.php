<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Expense');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('ViewAll:Expense')
            || ($user->can('View:Expense') && $expense->recorded_by === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Expense');
    }

    // Catatan uang tidak pernah diubah atau dihapus, hanya dibatalkan.
    public function update(User $user, Expense $expense): bool
    {
        return false;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return false;
    }

    // Pembatalan hanya Owner (aturan keras).
    public function cancel(User $user, Expense $expense): bool
    {
        return $user->hasRole(UserRole::Owner->value) && ! $expense->isCancelled();
    }
}
