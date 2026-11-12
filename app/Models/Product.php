<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const LOW_STOCK = 5;

    protected $fillable = ['name', 'sku', 'price', 'track_stock', 'stock', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'track_stock' => 'boolean',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock <= self::LOW_STOCK;
    }

    public function isOutOfStock(): bool
    {
        return $this->track_stock && $this->stock <= 0;
    }
}
