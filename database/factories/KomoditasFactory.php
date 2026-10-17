<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'nama_komoditas' => ucfirst($name),
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'kategori' => fake()->randomElement(['Beras & Padi', 'Biji-bijian', 'Minyak & Lemak', 'Gula & Pemanis']),
            'satuan' => fake()->randomElement(['kg', 'ton', 'sak', 'liter']),
            'kode_referensi_api' => (string) fake()->numberBetween(100, 999),
            'spesifikasi' => fake()->sentence(),
            'status_aktif' => true,
        ];
    }
}
