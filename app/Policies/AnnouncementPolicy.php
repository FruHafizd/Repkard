<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Announcement');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->can('View:Announcement');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Announcement');
    }

    /**
     * Pemilik hanya bisa mengubah pengumuman miliknya sendiri,
     * kecuali punya izin "kelola semua" (aturan keras, tidak bisa diubah lewat halaman Role).
     */
    public function update(User $user, Announcement $announcement): bool
    {
        return $user->can('Update:Announcement')
            && ($announcement->created_by === $user->getKey() || $user->can('ManageAll:Announcement'));
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->can('Delete:Announcement');
    }
}
