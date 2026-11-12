<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ExpenseCategory');
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $user->can('View:ExpenseCategory');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ExpenseCategory');
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->can('Update:ExpenseCategory');
    }

    // Kategori yang sudah dipakai tidak boleh dihapus (riwayat pengeluaran); cukup dinonaktifkan.
    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $user->can('Delete:ExpenseCategory') && ! $category->expenses()->exists();
    }
}
