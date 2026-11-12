<?php

namespace App\Models;

use App\Enums\FollowUpChannel;
use App\Enums\FollowUpOutcome;
use Database\Factories\MemberFollowUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberFollowUp extends Model
{
    /** @use HasFactory<MemberFollowUpFactory> */
    use HasFactory;

    protected $fillable = [
        'member_id',
        'user_id',
        'contacted_at',
        'channel',
        'outcome',
        'note',
        'next_contact_on',
    ];

    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
            'channel' => FollowUpChannel::class,
            'outcome' => FollowUpOutcome::class,
            'next_contact_on' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
