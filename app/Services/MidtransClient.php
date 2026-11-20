<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for Midtrans Snap and the transaction status API.
 * Sandbox and Production differ only by `services.midtrans.is_production`.
 */
class MidtransClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key'));
    }

    public function clientKey(): string
    {
        return (string) config('services.midtrans.client_key');
    }

    public function snapScriptUrl(): string
    {
        return $this->isProduction()
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    /**
     * Create a Snap transaction. `gross_amount` is whole rupiah, as Midtrans requires.
     *
     * @param  array<string, mixed>  $payload
     * @return array{token: string, redirect_url: string}
     *
     * @throws MidtransException
     */
    public function createSnapTransaction(array $payload): array
    {
        $body = $this->send(fn (PendingRequest $http) => $http->post($this->snapBaseUrl().'/snap/v1/transactions', $payload));

        if (! is_string($body['token'] ?? null)) {
            throw new MidtransException('Midtrans tidak mengembalikan token pembayaran.');
        }

        return ['token' => $body['token'], 'redirect_url' => (string) ($body['redirect_url'] ?? '')];
    }

    /**
     * Current status of a transaction by its order id.
     *
     * @return array<string, mixed>
     *
     * @throws MidtransException
     */
    public function transactionStatus(string $orderId): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get($this->apiBaseUrl().'/v2/'.rawurlencode($orderId).'/status'));
    }

    /**
     * Callback signature: SHA512(order_id + status_code + gross_amount + server key) (NFR-04).
     *
     * @param  array<string, mixed>  $payload
     */
    public function isValidSignature(array $payload): bool
    {
        $signature = $payload['signature_key'] ?? null;

        if (! is_string($signature) || ! $this->isConfigured()) {
            return false;
        }

        $expected = hash('sha512', ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').config('services.midtrans.server_key'));

        return hash_equals($expected, $signature);
    }

    private function isProduction(): bool
    {
        return (bool) config('services.midtrans.is_production');
    }

    private function snapBaseUrl(): string
    {
        return $this->isProduction() ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    private function apiBaseUrl(): string
    {
        return $this->isProduction() ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     *
     * @throws MidtransException
     */
    private function send(callable $request): array
    {
        if (! $this->isConfigured()) {
            throw new MidtransException('Kredensial Midtrans belum diatur di .env.');
        }

        $http = Http::withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.midtrans.timeout'));

        try {
            $response = $request($http);
        } catch (ConnectionException $e) {
            throw new MidtransException('Midtrans tidak dapat dihubungi.', 0, $e);
        }

        if ($response->failed()) {
            throw new MidtransException('Midtrans menolak permintaan (HTTP '.$response->status().'): '.($response->json('error_messages.0') ?? $response->json('status_message') ?? 'tanpa pesan').'.');
        }

        return (array) $response->json();
    }
}
