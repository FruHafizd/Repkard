<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengumuman dari owner/pegawai untuk member (dan landing page bila ditandai).
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'starts_at',
        'ends_at',
        'is_pinned',
        'show_on_landing',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_pinned' => 'boolean',
            'show_on_landing' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Pengumuman yang sedang tayang: sudah mulai dan belum berakhir.
     * Disematkan di atas, lalu yang terbaru.
     */
    public function scopePublished(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('starts_at', '<=', $now)
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderByDesc('is_pinned')
            ->orderByDesc('starts_at');
    }

    public function isScheduled(): bool
    {
        return $this->starts_at->isFuture();
    }

    public function isEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /** Status untuk ditampilkan: Terjadwal, Berakhir, atau Tayang. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->isScheduled() => 'Terjadwal',
            $this->isEnded() => 'Berakhir',
            default => 'Tayang',
        };
    }
}
