<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommodityCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommodityCategory>
 */
class CommodityCategoryFactory extends Factory
{
    protected $model = CommodityCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
