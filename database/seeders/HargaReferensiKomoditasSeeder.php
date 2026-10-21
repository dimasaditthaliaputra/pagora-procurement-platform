<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HargaReferensiKomoditas;
use App\Models\Komoditas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo seeder: hanya local/testing.
 * Membuat 91 hari riwayat harga dummy per komoditas (tren linier + gelombang mingguan).
 */
class HargaReferensiKomoditasSeeder extends Seeder
{
    /**
     * Harga dasar per komoditas (Rp/satuan), dan tren harian (Rp/hari).
     *
     * @return array<string, array{base: float, trend: float}>
     */
    private function hargaDasar(): array
    {
        return [
            'BERAS' => ['base' => 12500, 'trend' => 10.0],
            'BAWANG_MERAH' => ['base' => 28000, 'trend' => 30.0],
            'BAWANG_PUTIH' => ['base' => 32000, 'trend' => 25.0],
            'CABAI_MERAH' => ['base' => 38000, 'trend' => 50.0],
            'CABAI_RAWIT' => ['base' => 55000, 'trend' => 60.0],
            'DAGING_AYAM' => ['base' => 32000, 'trend' => 15.0],
            'DAGING_SAPI' => ['base' => 120000, 'trend' => 50.0],
            'TELUR_AYAM' => ['base' => 28000, 'trend' => 10.0],
            'GULA_PASIR' => ['base' => 14000, 'trend' => 5.0],
            'MINYAK_GORENG' => ['base' => 15000, 'trend' => 8.0],
        ];
    }

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $komoditasMap = Komoditas::whereIn('sku', array_keys($this->hargaDasar()))
            ->get()
            ->keyBy('sku');

        $now = now();

        foreach ($this->hargaDasar() as $sku => $cfg) {
            $komoditas = $komoditasMap[$sku] ?? null;
            if (! $komoditas) {
                continue;
            }

            HargaReferensiKomoditas::where('komoditas_id', $komoditas->id)
                ->where('sumber_data', 'seeder')
                ->delete();

            $rows = [];

            for ($hari = 90; $hari >= 0; $hari--) {
                $hariKe = 90 - $hari;

                $harga = $cfg['base']
                    + ($cfg['trend'] * $hariKe)
                    + (sin($hariKe * M_PI / 7) * $cfg['base'] * 0.02);

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'komoditas_id' => $komoditas->id,
                    'tanggal' => now()->subDays($hari)->toDateString(),
                    'harga' => round($harga, 2),
                    'sumber_data' => 'seeder',
                    'created_at' => $now,
                ];
            }

            HargaReferensiKomoditas::insert($rows);
        }
    }
}
