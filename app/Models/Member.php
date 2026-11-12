<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Services\MemberPhotoService;
use Carbon\Carbon;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'phone',
        'gender',
        'birth_date',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo_path',
        'status',
        'selected_plan_id',
        'requested_promo_code',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status' => MemberStatus::class,
            'activated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // File foto ikut dihapus hanya saat member benar-benar dihapus permanen.
        static::forceDeleted(function (Member $member): void {
            app(MemberPhotoService::class)->delete($member->photo_path);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function selectedPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'selected_plan_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(MemberCard::class);
    }

    public function activeCard(): ?MemberCard
    {
        return $this->cards()->whereNull('revoked_at')->orderByDesc('version')->first();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function ptSubscriptions(): HasMany
    {
        return $this->hasMany(PtSubscription::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(MemberFollowUp::class);
    }

    public function latestFollowUp(): HasOne
    {
        return $this->hasOne(MemberFollowUp::class)->latestOfMany('contacted_at');
    }

    /**
     * Member aktif dengan membership berlaku, tidak beku, kuota belum habis,
     * dan sudah >= $days hari tanpa check-in diterima (atau tanpa check-in sejak diaktifkan).
     * Menambahkan atribut `last_visit_at` dan `days_absent` (hari penuh sejak kunjungan terakhir).
     */
    public function scopeMulaiMenghilang(Builder $query, int $days): Builder
    {
        $today = now()->toDateString();
        // Batas inklusif: kunjungan terakhir pada tanggal (hari ini - $days) sudah dihitung $days hari.
        $cutoff = now()->subDays($days)->endOfDay();

        $lastVisit = CheckIn::query()
            ->selectRaw('MAX(checked_in_at)')
            ->whereColumn('check_ins.member_id', 'members.id')
            ->where('result', CheckIn::RESULT_ACCEPTED);

        $lastVisitSql = $lastVisit->toSql();
        $absentSinceSql = "COALESCE(({$lastVisitSql}), members.activated_at)";
        $bindings = $lastVisit->getBindings();

        return $query
            ->where('members.status', MemberStatus::Active)
            ->whereHas('memberships', fn (Builder $q) => $q
                ->whereNull('cancelled_at')
                ->whereDate('starts_on', '<=', $today)
                ->whereDate('ends_on', '>=', $today)
                ->where(fn (Builder $q) => $q->whereNull('visits_remaining')->orWhere('visits_remaining', '!=', 0))
                ->whereNot(fn (Builder $q) => $q
                    ->whereNotNull('frozen_from')
                    ->whereNotNull('frozen_until')
                    ->whereDate('frozen_from', '<=', $today)
                    ->whereDate('frozen_until', '>=', $today)))
            ->addSelect('members.*')
            ->selectSub($lastVisit, 'last_visit_at')
            ->selectRaw("{$absentSinceSql} as absent_since", $bindings)
            ->whereRaw("{$absentSinceSql} <= ?", [...$bindings, $cutoff]);
    }

    /** Tanggal kunjungan terakhir (hanya dari scopeMulaiMenghilang). */
    protected function lastVisitAt(): Attribute
    {
        return Attribute::get(fn ($value) => $value ? Carbon::parse($value) : null);
    }

    /** Hari penuh tanpa kunjungan sejak kunjungan terakhir / aktivasi (hanya dari scopeMulaiMenghilang). */
    protected function daysAbsent(): Attribute
    {
        return Attribute::get(function (): ?int {
            $since = $this->attributes['absent_since'] ?? null;

            return $since ? (int) Carbon::parse($since)->startOfDay()->diffInDays(now()->startOfDay()) : null;
        });
    }

    /**
     * Membership yang belum dibatalkan dan masa berlakunya mencakup hari ini.
     */
    public function currentMembership(): ?Membership
    {
        $today = now()->toDateString();

        return $this->memberships()
            ->whereNull('cancelled_at')
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->orderByDesc('ends_on')
            ->first();
    }

    /**
     * URL foto lewat route yang dicek izinnya. Query "v" memecah cache saat foto diganti.
     */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return route('member.photo', ['member' => $this, 'v' => $this->updated_at?->timestamp]);
    }
}
