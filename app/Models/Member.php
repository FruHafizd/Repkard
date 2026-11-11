<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Services\MemberPhotoService;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
