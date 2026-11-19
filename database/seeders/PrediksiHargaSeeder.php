<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HargaReferensiKomoditas;
use App\Models\Komoditas;
use App\Models\PrediksiHarga;
use App\Services\PricePredictionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo seeder: hanya local/testing.
 * Regresi linier sederhana atas riwayat harga dummy, prediksi harian untuk horizon Harga Pagu.
 * Tidak memanggil price service; Harga Pagu memakai rumus yang sama dengan aplikasi (design-db §8.5).
 */
class PrediksiHargaSeeder extends Seeder
{
    public function run(PricePredictionService $pricePredictionService): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $calculatedAt = now();

        foreach (Komoditas::where('is_active', true)->get() as $komoditas) {
            $prices = HargaReferensiKomoditas::where('commodity_id', $komoditas->id)
                ->where('source', HargaReferensiKomoditas::SOURCE_SEEDER)
                ->orderBy('date')
                ->pluck('price')
                ->map(fn ($price): float => (float) $price)
                ->values();

            $n = $prices->count();
            if ($n < 2) {
                continue;
            }

            $meanX = ($n - 1) / 2;
            $meanY = $prices->avg();
            $covariance = 0.0;
            $variance = 0.0;

            foreach ($prices as $x => $y) {
                $covariance += ($x - $meanX) * ($y - $meanY);
                $variance += ($x - $meanX) ** 2;
            }

            $slope = $covariance / $variance;
            $intercept = $meanY - ($slope * $meanX);

            PrediksiHarga::where('commodity_id', $komoditas->id)
                ->whereDoesntHave('purchaseOrders')
                ->delete();

            $rows = [];
            for ($day = 1; $day <= PricePredictionService::HORIZON_DAYS; $day++) {
                $predicted = round($intercept + ($slope * ($n - 1 + $day)), 2);

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'commodity_id' => $komoditas->id,
                    'prediction_date' => now()->addDays($day)->toDateString(),
                    'predicted_price' => $predicted,
                    'recommended_ceiling_price' => $pricePredictionService->ceilingPrice($predicted),
                    'calculated_at' => $calculatedAt,
                ];
            }

            PrediksiHarga::insert($rows);
        }
    }
}
