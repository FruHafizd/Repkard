<?php

namespace App\Policies;

use App\Models\PtPackage;
use App\Models\User;

class PtPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:PtPackage');
    }

    public function view(User $user, PtPackage $ptPackage): bool
    {
        return $user->can('View:PtPackage');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:PtPackage');
    }

    public function update(User $user, PtPackage $ptPackage): bool
    {
        return $user->can('Update:PtPackage');
    }

    // Paket yang sudah terjual hanya dinonaktifkan, tidak dihapus.
    public function delete(User $user, PtPackage $ptPackage): bool
    {
        return $user->can('Delete:PtPackage') && ! $ptPackage->subscriptions()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, PtPackage $ptPackage): bool
    {
        return false;
    }

    public function forceDelete(User $user, PtPackage $ptPackage): bool
    {
        return false;
    }
}
