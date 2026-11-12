<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Product');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('View:Product');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Product');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('Update:Product');
    }

    // Produk yang pernah terjual tidak boleh dihapus (riwayat transaksi); cukup dinonaktifkan.
    public function delete(User $user, Product $product): bool
    {
        return $user->can('Delete:Product') && ! $product->saleItems()->exists();
    }

    public function manageStock(User $user, Product $product): bool
    {
        return $user->can('ManageStock:Product');
    }
}
