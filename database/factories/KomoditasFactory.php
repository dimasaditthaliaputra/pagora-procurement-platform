<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommodityCategory;
use App\Models\Komoditas;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Komoditas>
 */
class KomoditasFactory extends Factory
{
    protected $model = Komoditas::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'uuid' => (string) Str::uuid(),
            'slug' => Str::slug($name),
            'name' => ucfirst($name),
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'commodity_category_id' => CommodityCategory::factory(),
            'unit' => fake()->randomElement(['kg', 'ton', 'sak', 'liter']),
            'default_shrinkage_tolerance' => fake()->randomFloat(2, 0, 10),
            'api_reference_code' => (string) fake()->numberBetween(100, 999),
            'specification' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
