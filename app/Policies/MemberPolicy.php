<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    private function milikSendiri(User $user, Member $member): bool
    {
        return $member->user_id === $user->getKey();
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Member');
    }

    public function view(User $user, Member $member): bool
    {
        return $this->milikSendiri($user, $member) || $user->can('View:Member');
    }

    public function viewPhoto(User $user, Member $member): bool
    {
        return $this->milikSendiri($user, $member) || $user->can('View:Member');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Member');
    }

    public function update(User $user, Member $member): bool
    {
        return $user->can('Update:Member');
    }

    public function delete(User $user, Member $member): bool
    {
        return $user->can('Delete:Member');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Member');
    }

    public function restore(User $user, Member $member): bool
    {
        return $user->can('Restore:Member');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Member');
    }

    public function forceDelete(User $user, Member $member): bool
    {
        return $user->can('ForceDelete:Member');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Member');
    }

    public function downloadCard(User $user, Member $member): bool
    {
        return $this->milikSendiri($user, $member) || $user->can('View:Member');
    }

    public function refund(User $user, Member $member): bool
    {
        return $user->can('Refund:Member');
    }

    public function sellPt(User $user, Member $member): bool
    {
        return $user->can('SellPt:Member');
    }

    public function reissueCard(User $user, Member $member): bool
    {
        return $user->can('ReissueCard:Member');
    }

    /**
     * Pencabutan hanya Owner (aturan keras, tidak bisa diubah lewat halaman Role).
     */
    public function revokeCard(User $user, Member $member): bool
    {
        return $user->hasRole(UserRole::Owner->value);
    }
}
