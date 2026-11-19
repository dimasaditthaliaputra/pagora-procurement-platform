<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the PAGORA price service (`/v1/prices`, `/v1/forecast`).
 * Contract: `context.md` §4 in the `pagora-price-service` repository.
 */
class PriceServiceClient
{
    /** PIHPS commodity code format, mirrored in the Komoditas form requests. */
    public const CODE_PATTERN = '/^com_\d+$/';

    public function isConfigured(): bool
    {
        return filled(config('services.price_service.url')) && filled(config('services.price_service.key'));
    }

    /**
     * Commodities published by Bank Indonesia PIHPS (`code` = `com_N`).
     *
     * @return list<array{code: string, name: string, category: ?string, unit: string}>
     *
     * @throws PriceServiceException
     */
    public function commodities(): array
    {
        $body = $this->get('/v1/commodities', []);

        $commodities = [];
        foreach ($body as $item) {
            $code = $item['code'] ?? null;
            $name = trim((string) ($item['name'] ?? ''));

            if (is_string($code) && preg_match(self::CODE_PATTERN, $code) && $name !== '') {
                $commodities[] = [
                    'code' => $code,
                    'name' => $name,
                    'category' => is_string($item['category'] ?? null) ? trim($item['category']) : null,
                    'unit' => is_string($item['unit'] ?? null) && $item['unit'] !== '' ? $item['unit'] : 'kg',
                ];
            }
        }

        if ($commodities === []) {
            throw new PriceServiceException(PriceServiceException::UNAVAILABLE, 'Price service returned no commodities.');
        }

        return $commodities;
    }

    /**
     * Daily national Bank Indonesia prices, oldest first.
     *
     * @return list<array{date: string, price: float}>
     *
     * @throws PriceServiceException
     */
    public function prices(string $code, CarbonInterface $from, CarbonInterface $to): array
    {
        $body = $this->get('/v1/prices', [
            'code' => $code,
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ]);

        return $this->points($body['prices'] ?? null);
    }

    /**
     * Daily forecast for the next `$horizonDays` calendar days.
     *
     * @return array{history_end: ?string, syncing: bool, predictions: list<array{date: string, price: float}>}
     *
     * @throws PriceServiceException
     */
    public function forecast(string $code, int $horizonDays): array
    {
        $body = $this->get('/v1/forecast', ['code' => $code, 'horizon_days' => $horizonDays]);
        $predictions = $this->points($body['predictions'] ?? null);

        if ($predictions === []) {
            throw new PriceServiceException(PriceServiceException::UNAVAILABLE, 'Price service returned no predictions.');
        }

        return [
            'history_end' => is_string($body['history_end'] ?? null) ? $body['history_end'] : null,
            'syncing' => (bool) ($body['syncing'] ?? false),
            'predictions' => $predictions,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws PriceServiceException
     */
    private function get(string $path, array $query): array
    {
        if (! $this->isConfigured()) {
            throw new PriceServiceException(PriceServiceException::NOT_CONFIGURED, 'PRICE_SERVICE_URL / PRICE_SERVICE_API_KEY is not set.');
        }

        try {
            $response = $this->request()->get($path, $query);
        } catch (ConnectionException $e) {
            throw new PriceServiceException(PriceServiceException::UNAVAILABLE, "Price service unreachable: {$e->getMessage()}");
        }

        if ($response->successful() && is_array($response->json())) {
            return $response->json();
        }

        throw $this->failure($response);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.price_service.url'), '/'))
            ->withHeaders(['X-API-Key' => (string) config('services.price_service.key')])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout((int) config('services.price_service.timeout', 120));
    }

    private function failure(Response $response): PriceServiceException
    {
        $body = $response->json();
        $detail = is_array($body) ? ($body['message'] ?? $body['detail'] ?? null) : null;
        $detail = is_string($detail) ? $detail : "HTTP {$response->status()}";
        $retryAfter = is_numeric($response->header('Retry-After')) ? (int) $response->header('Retry-After') : null;

        return match (true) {
            $response->status() === 503 && ($body['status'] ?? null) === 'syncing' => new PriceServiceException(PriceServiceException::SYNCING, $detail, $retryAfter),
            $response->status() === 422 => new PriceServiceException(PriceServiceException::INSUFFICIENT_DATA, $detail),
            default => new PriceServiceException(PriceServiceException::UNAVAILABLE, "Price service error ({$response->status()}): {$detail}", $retryAfter),
        };
    }

    /**
     * @return list<array{date: string, price: float}>
     */
    private function points(mixed $points): array
    {
        if (! is_array($points)) {
            throw new PriceServiceException(PriceServiceException::UNAVAILABLE, 'Price service returned an unexpected payload.');
        }

        $valid = [];
        foreach ($points as $point) {
            $date = $point['date'] ?? null;
            $price = $point['price'] ?? null;

            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && is_numeric($price) && (float) $price > 0) {
                $valid[] = ['date' => $date, 'price' => round((float) $price, 2)];
            }
        }

        return $valid;
    }
}
