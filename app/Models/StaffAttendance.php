<?php

namespace App\Models;

use Database\Factories\StaffAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Absen masuk / pulang pegawai & trainer, dengan swafoto (disk private) saat masuk dan pulang.
 */
class StaffAttendance extends Model
{
    /** @use HasFactory<StaffAttendanceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'clock_in_at',
        'clock_in_photo_path',
        'clock_out_at',
        'clock_out_photo_path',
        'is_late',
        'corrected_by',
        'correction_reason',
    ];

    protected function casts(): array
    {
        return [
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /** Hari absen masuk sudah lewat dan belum ada absen pulang. */
    public function isForgotClockOut(): bool
    {
        return $this->clock_out_at === null
            && $this->clock_in_at->toDateString() < now()->toDateString();
    }

    public function isCorrected(): bool
    {
        return $this->corrected_by !== null;
    }

    public function workedMinutes(): ?int
    {
        return $this->clock_out_at
            ? (int) $this->clock_in_at->diffInMinutes($this->clock_out_at, true)
            : null;
    }

    /** Mis. "8 j 30 m", atau null bila belum pulang. */
    public function durationLabel(): ?string
    {
        $minutes = $this->workedMinutes();

        return $minutes === null ? null : self::formatMinutes($minutes);
    }

    public static function formatMinutes(int $minutes): string
    {
        return intdiv($minutes, 60).' j '.($minutes % 60).' m';
    }
}
