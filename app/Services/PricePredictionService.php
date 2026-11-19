<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HargaReferensiKomoditas;
use App\Models\Komoditas;
use App\Models\PrediksiHarga;
use App\Models\User;
use App\Repositories\Contracts\HargaReferensiKomoditasRepositoryInterface;
use App\Repositories\Contracts\KomoditasRepositoryInterface;
use App\Repositories\Contracts\PrediksiHargaRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Price trend & Harga Pagu (UC-11, FR-15, FR-16, BR-01, BR-24).
 *
 * Prices and forecasts come from the price service and are stored locally; the dashboard
 * only reads the database, so it keeps working with the last data when the service or
 * Bank Indonesia is down.
 */
class PricePredictionService
{
    // TODO: validasi margin Harga Pagu (design-db §12 no. 1).
    public const CEILING_MARGIN = 1.05;

    /** Harga Pagu = highest prediction in this window × margin (owner decision 2026-11-19). */
    public const HORIZON_DAYS = 30;

    /** History pulled the first time a commodity is synced; later syncs only fetch new days. */
    public const BACKFILL_DAYS = 180;

    public const CHART_HISTORY_DAYS = 90;

    public const STATUS_UPDATED = 'updated';

    public const STATUS_NOT_MAPPED = 'not_mapped';

    private const SYNC_STATUS_CACHE_KEY = 'harga_pagu:sync:';

    public function __construct(
        protected PriceServiceClient $client,
        protected KomoditasRepositoryInterface $komoditas,
        protected HargaReferensiKomoditasRepositoryInterface $referencePrices,
        protected PrediksiHargaRepositoryInterface $predictions,
    ) {}

    /**
     * The single place the Harga Pagu formula lives (design-db §8.5).
     */
    public function ceilingPrice(float $predictedPrice): float
    {
        return round($predictedPrice * self::CEILING_MARGIN, 2);
    }

    /**
     * Current Harga Pagu row for a commodity: the highest ceiling of its latest forecast run.
     */
    public function latestCeiling(int $commodityId): ?PrediksiHarga
    {
        return $this->predictions->latestCeiling($commodityId);
    }

    /**
     * Sync every active commodity mapped to a BI code, one after another (BI etiquette).
     *
     * @return array<string, array{status: string, message: string}>
     */
    public function syncAll(): array
    {
        return $this->komoditas->getActiveWithApiReference()
            ->mapWithKeys(fn (Komoditas $komoditas): array => [$komoditas->name => $this->syncCommodity($komoditas)])
            ->all();
    }

    /**
     * Pull new BI prices, then store a fresh forecast run.
     *
     * @return array{status: string, message: string}
     */
    public function syncCommodity(Komoditas $komoditas): array
    {
        $code = trim((string) $komoditas->api_reference_code);

        if ($code === '') {
            return $this->remember($komoditas, self::STATUS_NOT_MAPPED, 'Komoditas ini belum dipetakan ke kode komoditas Bank Indonesia.');
        }

        $pricesError = null;

        try {
            $this->syncPrices($komoditas, $code);
        } catch (PriceServiceException $e) {
            $pricesError = $e;
            $this->logFailure($komoditas, 'prices', $e);
        }

        try {
            $forecast = $this->client->forecast($code, self::HORIZON_DAYS);
        } catch (PriceServiceException $e) {
            $this->logFailure($komoditas, 'forecast', $e);

            return $this->remember($komoditas, $e->reason, $this->messageFor($e));
        }

        $this->storeForecast($komoditas, $forecast['predictions']);

        $message = 'Data harga dan prediksi berhasil diperbarui.';
        if ($pricesError !== null) {
            $message = 'Prediksi diperbarui, tetapi riwayat harga terbaru dari Bank Indonesia belum dapat diambil.';
        }

        return $this->remember($komoditas, self::STATUS_UPDATED, $message);
    }

    /**
     * On-demand update of one commodity from the dashboard ("Perbarui data").
     *
     * @return array{status: string, message: string}
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function refresh(User $actor, string $commodityUuid): array
    {
        Gate::forUser($actor)->authorize('refresh', PrediksiHarga::class);

        return $this->syncCommodity($this->findForTenant($actor, $commodityUuid));
    }

    /**
     * Data for the Dashboard Analitik page.
     *
     * @return array{
     *     commodity_options: list<array{value: string, label: string}>,
     *     commodity: Komoditas|null,
     *     history: Collection<int, HargaReferensiKomoditas>,
     *     predictions: Collection<int, PrediksiHarga>,
     *     ceiling: PrediksiHarga|null,
     *     latest_price: HargaReferensiKomoditas|null,
     *     notice: array{type: string, message: string}|null,
     * }
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function dashboard(User $actor, ?string $commodityUuid): array
    {
        Gate::forUser($actor)->authorize('viewAny', PrediksiHarga::class);

        $commodities = $this->komoditas->getActiveForTenant((int) $actor->tenant_id);
        $commodity = $commodityUuid !== null
            ? ($commodities->firstWhere('uuid', $commodityUuid) ?? throw (new ModelNotFoundException)->setModel(Komoditas::class, [$commodityUuid]))
            : $commodities->first();

        $history = collect();
        $predictions = collect();
        $ceiling = null;
        $latestPrice = null;

        if ($commodity !== null) {
            $history = $this->referencePrices->history($commodity->id, now()->subDays(self::CHART_HISTORY_DAYS)->startOfDay());
            $predictions = $this->predictions->latestBatch($commodity->id);
            $ceiling = $this->predictions->latestCeiling($commodity->id);
            $latestPrice = $this->referencePrices->latest($commodity->id);
        }

        return [
            'commodity_options' => $commodities
                ->map(fn (Komoditas $item): array => ['value' => $item->uuid, 'label' => $item->name])
                ->values()
                ->all(),
            'commodity' => $commodity,
            'history' => $history,
            'predictions' => $predictions,
            'ceiling' => $ceiling,
            'latest_price' => $latestPrice,
            'notice' => $commodity ? $this->notice($commodity, $latestPrice, $ceiling) : null,
        ];
    }

    /**
     * @throws ModelNotFoundException
     */
    private function findForTenant(User $actor, string $commodityUuid): Komoditas
    {
        return $this->komoditas->getActiveForTenant((int) $actor->tenant_id)->firstWhere('uuid', $commodityUuid)
            ?? throw (new ModelNotFoundException)->setModel(Komoditas::class, [$commodityUuid]);
    }

    /**
     * @throws PriceServiceException
     */
    private function syncPrices(Komoditas $komoditas, string $code): void
    {
        $today = now()->startOfDay();
        $backfillFrom = $today->copy()->subDays(self::BACKFILL_DAYS);
        $lastDate = $this->referencePrices->latestDate($komoditas->id, HargaReferensiKomoditas::SOURCE_BANK_INDONESIA);

        $from = $lastDate !== null && $lastDate->greaterThan($backfillFrom)
            ? Carbon::instance($lastDate)->addDay()
            : $backfillFrom;

        if ($from->greaterThan($today)) {
            return;
        }

        $this->referencePrices->upsertMany(
            $komoditas->id,
            HargaReferensiKomoditas::SOURCE_BANK_INDONESIA,
            $this->client->prices($code, $from, $today)
        );
    }

    /**
     * @param  list<array{date: string, price: float}>  $predictions
     */
    private function storeForecast(Komoditas $komoditas, array $predictions): void
    {
        $calculatedAt = now();

        $this->predictions->createBatch(array_map(fn (array $point): array => [
            'commodity_id' => $komoditas->id,
            'prediction_date' => $point['date'],
            'predicted_price' => $point['price'],
            'recommended_ceiling_price' => $this->ceilingPrice($point['price']),
            'calculated_at' => $calculatedAt,
        ], $predictions));
    }

    /**
     * UC-11 alternative flows, from most to least severe.
     *
     * @return array{type: string, message: string}|null
     */
    private function notice(Komoditas $komoditas, ?HargaReferensiKomoditas $latestPrice, ?PrediksiHarga $ceiling): ?array
    {
        $lastSync = Cache::get(self::SYNC_STATUS_CACHE_KEY.$komoditas->id);
        $status = $lastSync['status'] ?? null;

        if ($status === PriceServiceException::SYNCING) {
            return ['type' => 'info', 'message' => $lastSync['message']];
        }

        if ($latestPrice === null && $ceiling === null) {
            return ['type' => 'warning', 'message' => 'Data harga untuk komoditas ini belum tersedia.'];
        }

        if ($status === PriceServiceException::UNAVAILABLE) {
            $lastUpdate = $ceiling?->calculated_at ?? $latestPrice?->created_at;

            return [
                'type' => 'warning',
                'message' => 'Data harga terbaru tidak dapat diambil. Menampilkan data terakhir per '
                    .($lastUpdate ? $lastUpdate->timezone('Asia/Jakarta')->translatedFormat('d F Y H:i').' WIB' : '-').'.',
            ];
        }

        if ($ceiling === null) {
            return ['type' => 'warning', 'message' => 'Prediksi harga untuk komoditas ini belum dapat dihitung.'];
        }

        return null;
    }

    private function messageFor(PriceServiceException $e): string
    {
        return match ($e->reason) {
            PriceServiceException::SYNCING => $e->getMessage(),
            PriceServiceException::INSUFFICIENT_DATA => 'Prediksi harga untuk komoditas ini belum dapat dihitung.',
            PriceServiceException::NOT_CONFIGURED => 'Layanan prediksi harga belum dikonfigurasi.',
            default => 'Data harga terbaru tidak dapat diambil. Silakan coba lagi nanti.',
        };
    }

    /**
     * Last sync outcome per commodity, read by the dashboard to pick the UC-11 notice.
     *
     * @return array{status: string, message: string}
     */
    private function remember(Komoditas $komoditas, string $status, string $message): array
    {
        $result = ['status' => $status, 'message' => $message];
        Cache::forever(self::SYNC_STATUS_CACHE_KEY.$komoditas->id, [...$result, 'attempted_at' => now()->toIso8601String()]);

        return $result;
    }

    private function logFailure(Komoditas $komoditas, string $step, PriceServiceException $e): void
    {
        $level = $e->reason === PriceServiceException::SYNCING ? 'info' : 'warning';

        Log::log($level, "Harga Pagu sync ({$step}) failed for commodity {$komoditas->uuid}.", [
            'code' => $komoditas->api_reference_code,
            'reason' => $e->reason,
            'detail' => $e->getMessage(),
        ]);
    }
}
