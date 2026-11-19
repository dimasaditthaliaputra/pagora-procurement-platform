<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PricePredictionService;
use Illuminate\Console\Command;

class SyncHargaPagu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'harga-pagu:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ambil harga BI terbaru dan prediksi Harga Pagu dari price service untuk semua komoditas yang dipetakan';

    /**
     * Execute the console command.
     */
    public function handle(PricePredictionService $service): int
    {
        $results = $service->syncAll();

        if ($results === []) {
            $this->warn('Tidak ada komoditas aktif yang dipetakan ke kode Bank Indonesia (api_reference_code).');

            return self::SUCCESS;
        }

        $this->table(
            ['Komoditas', 'Status', 'Keterangan'],
            collect($results)->map(fn (array $result, string $name): array => [$name, $result['status'], $result['message']])->values()->all()
        );

        $updated = collect($results)->where('status', PricePredictionService::STATUS_UPDATED)->count();
        $this->info("{$updated} dari ".count($results).' komoditas diperbarui.');

        return self::SUCCESS;
    }
}
