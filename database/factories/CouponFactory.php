<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('SAVE##??')),
            'description' => null,
            'type' => CouponType::Percent,
            'value' => 20,
            'currency' => 'USD',
            'applies_to' => CouponScope::All,
            'is_active' => true,
            'used_count' => 0,
        ];
    }

    public function percent(int $percent): self
    {
        return $this->state(['type' => CouponType::Percent, 'value' => $percent]);
    }

    /** @param int $cents the discount in cents, never a float */
    public function fixed(int $cents): self
    {
        return $this->state(['type' => CouponType::Fixed, 'value' => $cents]);
    }

    public function expired(): self
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }

    public function usedUp(): self
    {
        return $this->state(['usage_limit' => 1, 'used_count' => 1]);
    }
}
