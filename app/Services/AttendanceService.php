<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AttendanceService
{
    public const PHOTO_DIRECTORY = 'staff-attendance';

    public function __construct(private readonly MemberPhotoService $photos) {}

    /**
     * Absen masuk dengan swafoto. Absensi hari sebelumnya yang belum pulang tidak menghalangi
     * (tampil sebagai "Lupa pulang" di rekap).
     */
    public function clockIn(User $user, string $photoDataUrl): StaffAttendance
    {
        $this->ensureCanAttend($user);

        return DB::transaction(function () use ($user, $photoDataUrl): StaffAttendance {
            // Kunci baris user supaya klik ganda tidak membuat dua absensi terbuka.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if ($this->openToday($user) !== null) {
                throw ValidationException::withMessages(['attendance' => 'Anda sudah absen masuk hari ini']);
            }

            $path = $this->photos->storeFromDataUrl($photoDataUrl, self::PHOTO_DIRECTORY);

            try {
                $now = now();

                return StaffAttendance::create([
                    'user_id' => $user->getKey(),
                    'clock_in_at' => $now,
                    'clock_in_photo_path' => $path,
                    'is_late' => $this->isLate($user, $now),
                ]);
            } catch (Throwable $e) {
                $this->photos->delete($path);

                throw $e;
            }
        });
    }

    /**
     * Absen pulang dengan swafoto untuk absensi hari ini yang belum pulang.
     */
    public function clockOut(User $user, string $photoDataUrl): StaffAttendance
    {
        $this->ensureCanAttend($user);

        return DB::transaction(function () use ($user, $photoDataUrl): StaffAttendance {
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $attendance = $this->openToday($user);

            if ($attendance === null) {
                throw ValidationException::withMessages(['attendance' => 'Anda belum absen masuk hari ini']);
            }

            $path = $this->photos->storeFromDataUrl($photoDataUrl, self::PHOTO_DIRECTORY);

            try {
                $attendance->update([
                    'clock_out_at' => now(),
                    'clock_out_photo_path' => $path,
                ]);
            } catch (Throwable $e) {
                $this->photos->delete($path);

                throw $e;
            }

            return $attendance;
        });
    }

    /**
     * Koreksi jam oleh pemegang izin Correct:StaffAttendance (default: Owner). Alasan wajib.
     */
    public function correct(StaffAttendance $attendance, Carbon $in, ?Carbon $out, string $reason, User $by): StaffAttendance
    {
        if (! $by->can('Correct:StaffAttendance')) {
            throw new AuthorizationException('Anda tidak berhak mengoreksi absensi.');
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan koreksi wajib diisi.']);
        }

        if ($out !== null && $out->lessThanOrEqualTo($in)) {
            throw ValidationException::withMessages(['clock_out_at' => 'Jam pulang harus setelah jam masuk.']);
        }

        $attendance->update([
            'clock_in_at' => $in,
            'clock_out_at' => $out,
            'is_late' => $this->isLate($attendance->user, $in),
            'corrected_by' => $by->getKey(),
            'correction_reason' => trim($reason),
        ]);

        return $attendance;
    }

    /** Absensi hari ini (terakhir) milik user, baik sudah pulang maupun belum. */
    public function today(User $user): ?StaffAttendance
    {
        return StaffAttendance::query()
            ->where('user_id', $user->getKey())
            ->whereBetween('clock_in_at', [now()->startOfDay(), now()->endOfDay()])
            ->latest('clock_in_at')
            ->first();
    }

    public static function canAttend(User $user): bool
    {
        return $user->is_active
            && $user->hasAnyRole([UserRole::Pegawai->value, UserRole::Trainer->value]);
    }

    private function ensureCanAttend(User $user): void
    {
        if (! self::canAttend($user)) {
            throw ValidationException::withMessages(['attendance' => 'Hanya pegawai dan trainer aktif yang dapat absen.']);
        }
    }

    private function openToday(User $user): ?StaffAttendance
    {
        return StaffAttendance::query()
            ->where('user_id', $user->getKey())
            ->whereNull('clock_out_at')
            ->whereBetween('clock_in_at', [now()->startOfDay(), now()->endOfDay()])
            ->latest('clock_in_at')
            ->first();
    }

    /** Terlambat bila jam masuk (per menit) melewati jam mulai kerja + toleransi. */
    private function isLate(User $user, Carbon $clockIn): bool
    {
        if (blank($user->work_start_time)) {
            return false;
        }

        $limit = Carbon::parse($clockIn->toDateString().' '.$user->work_start_time)
            ->addMinutes((int) config('repkard.late_tolerance_minutes'));

        return $clockIn->copy()->startOfMinute()->greaterThan($limit);
    }
}
