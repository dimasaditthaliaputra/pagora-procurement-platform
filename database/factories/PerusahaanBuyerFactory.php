<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\PerusahaanBuyer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PerusahaanBuyer>
 */
class PerusahaanBuyerFactory extends Factory
{
    protected $model = PerusahaanBuyer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_name' => 'PT '.fake()->unique()->company(),
            'address' => fake()->address(),
            'verification_status' => VerificationStatus::Approved,
            'is_active' => true,
        ];
    }
}
