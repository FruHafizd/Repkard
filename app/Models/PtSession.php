<?php

namespace App\Models;

use App\Enums\PtSessionStatus;
use Database\Factories\PtSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtSession extends Model
{
    /** @use HasFactory<PtSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'pt_subscription_id',
        'trainer_id',
        'scheduled_at',
        'duration_minutes',
        'status',
        'commission_amount',
        'finished_at',
        'finished_by',
        'cancel_reason',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => PtSessionStatus::class,
            'commission_amount' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PtSubscription::class, 'pt_subscription_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
