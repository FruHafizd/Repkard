<?php

namespace App\Services;

use App\Models\PromoCode;

readonly class PromoResult
{
    public function __construct(
        public bool $valid,
        public string $message,
        public ?PromoCode $promo = null,
        public int $discount = 0,
        public int $finalPrice = 0,
    ) {}
}
