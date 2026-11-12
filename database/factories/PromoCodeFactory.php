<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO-####')),
            'name' => fake()->words(3, true),
            'discount_type' => DiscountType::Percent,
            'discount_value' => 10,
            'starts_on' => null,
            'ends_on' => null,
            'max_uses' => null,
            'max_uses_per_member' => 1,
            'new_members_only' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
