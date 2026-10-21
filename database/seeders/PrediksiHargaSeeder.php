<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HargaReferensiKomoditas;
use App\Models\Komoditas;
use App\Models\PrediksiHarga;
use Illuminate\Database\Seeder;

/**
 * Demo seeder: hanya local/testing.
 * Regresi linier sederhana atas riwayat harga dummy, prediksi 30 hari ke depan.
 */
class PrediksiHargaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $komoditasList = Komoditas::where('status', true)->get();

        foreach ($komoditasList as $komoditas) {
            $data = HargaReferensiKomoditas::where('komoditas_id', $komoditas->id)
                ->where('sumber_data', 'seeder')
                ->orderBy('tanggal')
                ->get(['tanggal', 'harga'])
                ->values();

            if ($data->count() < 2) {
                continue;
            }

            $n = $data->count();
            $sumX = 0;
            $sumY = 0.0;
            $sumXY = 0.0;
            $sumX2 = 0;

            foreach ($data as $i => $row) {
                $sumX += $i;
                $sumY += (float) $row->harga;
                $sumXY += $i * (float) $row->harga;
                $sumX2 += $i * $i;
            }

            $denom = ($n * $sumX2) - ($sumX * $sumX);
            if ($denom == 0) {
                continue;
            }

            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
            $intercept = ($sumY - ($slope * $sumX)) / $n;

            $hargaPrediksi = round($intercept + ($slope * ($n + 29)), 2);

            // TODO: sesuaikan rumus final Harga Pagu setelah ditetapkan tim
            $hargaPagu = round($hargaPrediksi * 1.05, 2);

            PrediksiHarga::updateOrCreate(
                [
                    'komoditas_id' => $komoditas->id,
                    'tanggal_prediksi' => now()->addDays(30)->toDateString(),
                ],
                [
                    'harga_prediksi' => $hargaPrediksi,
                    'harga_pagu_rekomendasi' => $hargaPagu,
                    'dihitung_pada' => now(),
                ]
            );
        }
    }
}
