<?php

namespace App\Models;

use Database\Factories\TrainerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerProfile extends Model
{
    /** @use HasFactory<TrainerProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialties',
        'bio',
        'commission_per_session',
    ];

    protected function casts(): array
    {
        return ['commission_per_session' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
