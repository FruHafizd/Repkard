<?php

namespace App\Models;

use Database\Factories\PtPackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PtPackage extends Model
{
    /** @use HasFactory<PtPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'sessions_count',
        'price',
        'validity_days',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sessions_count' => 'integer',
            'price' => 'integer',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PtSubscription::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
