<?php

namespace App\Models;

use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use HasFactory;

    public const RESULT_ACCEPTED = 'accepted';

    public const RESULT_REJECTED = 'rejected';

    protected $fillable = [
        'member_id',
        'member_card_id',
        'membership_id',
        'method',
        'result',
        'reject_reason',
        'manual_reason',
        'scanned_by',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(MemberCard::class, 'member_card_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
