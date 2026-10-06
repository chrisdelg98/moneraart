<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'type' => ProductType::Single,
            'price_cents' => fake()->numberBetween(199, 2999),
            'currency' => 'USD',
            'status' => ProductStatus::Draft,
            'is_ai_generated' => true,
            'license_type' => 'personal',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (): array => ['price_cents' => 0]);
    }

    public function onSale(int $cents = 199): static
    {
        return $this->state(fn (): array => [
            'sale_price_cents' => $cents,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addDay(),
        ]);
    }

    /** Adds a translation in the given locale. */
    public function withTranslation(string $locale = 'en', string $status = 'published'): static
    {
        return $this->afterCreating(function (Product $product) use ($locale, $status): void {
            $title = fake()->unique()->words(3, true);

            ProductTranslation::create([
                'product_id' => $product->id,
                'locale' => $locale,
                'title' => ucwords($title),
                'description' => fake()->paragraph(),
                'slug' => str($title)->slug()->toString(),
                'status' => $status,
            ]);
        });
    }
}
