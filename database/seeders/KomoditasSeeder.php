<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Komoditas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reference seeder: harus jalan di semua environment termasuk production.
 * Idempotent: updateOrCreate dengan kunci 'sku'.
 */
class KomoditasSeeder extends Seeder
{
    public function run(): void
    {
        $komoditas = [
            ['sku' => 'BERAS',         'nama' => 'Beras',         'kategori' => 'Pangan Pokok',    'satuan' => 'kg',    'batas_susut' => 1.0],
            ['sku' => 'BAWANG_MERAH',  'nama' => 'Bawang Merah',  'kategori' => 'Bumbu & Rempah',  'satuan' => 'kg',    'batas_susut' => 5.0],
            ['sku' => 'BAWANG_PUTIH',  'nama' => 'Bawang Putih',  'kategori' => 'Bumbu & Rempah',  'satuan' => 'kg',    'batas_susut' => 5.0],
            ['sku' => 'CABAI_MERAH',   'nama' => 'Cabai Merah',   'kategori' => 'Bumbu & Rempah',  'satuan' => 'kg',    'batas_susut' => 7.5],
            ['sku' => 'CABAI_RAWIT',   'nama' => 'Cabai Rawit',   'kategori' => 'Bumbu & Rempah',  'satuan' => 'kg',    'batas_susut' => 7.5],
            ['sku' => 'DAGING_AYAM',   'nama' => 'Daging Ayam',   'kategori' => 'Daging & Unggas', 'satuan' => 'kg',    'batas_susut' => 2.0],
            ['sku' => 'DAGING_SAPI',   'nama' => 'Daging Sapi',   'kategori' => 'Daging & Unggas', 'satuan' => 'kg',    'batas_susut' => 2.0],
            ['sku' => 'TELUR_AYAM',    'nama' => 'Telur Ayam',    'kategori' => 'Daging & Unggas', 'satuan' => 'kg',    'batas_susut' => 3.0],
            ['sku' => 'GULA_PASIR',    'nama' => 'Gula Pasir',    'kategori' => 'Pangan Pokok',    'satuan' => 'kg',    'batas_susut' => 0.5],
            ['sku' => 'MINYAK_GORENG', 'nama' => 'Minyak Goreng', 'kategori' => 'Pangan Pokok',    'satuan' => 'liter', 'batas_susut' => 0.5],
        ];

        foreach ($komoditas as $data) {
            Komoditas::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'slug' => Str::slug($data['nama']),
                    'nama_komoditas' => $data['nama'],
                    'kategori' => $data['kategori'],
                    'satuan' => $data['satuan'],
                    'batas_susut' => $data['batas_susut'],
                    'kode_referensi_api' => null, // TODO: isi setelah pemetaan ke API BI dikonfirmasi
                    'status' => true,
                ]
            );
        }
    }
}
