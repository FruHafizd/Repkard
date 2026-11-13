<?php

namespace App\Policies;

use App\Models\StaffAttendance;
use App\Models\User;

class StaffAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:StaffAttendance');
    }

    public function view(User $user, StaffAttendance $attendance): bool
    {
        return $user->can('ViewAll:StaffAttendance')
            || ($user->can('View:StaffAttendance') && $attendance->user_id === $user->getKey());
    }

    // Absensi hanya dibuat lewat halaman Absensi dan diubah lewat aksi Koreksi.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StaffAttendance $attendance): bool
    {
        return false;
    }

    public function delete(User $user, StaffAttendance $attendance): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function correct(User $user, StaffAttendance $attendance): bool
    {
        return $user->can('Correct:StaffAttendance');
    }
}
