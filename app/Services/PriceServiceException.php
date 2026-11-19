<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Failure talking to the price service, classified so the dashboard can pick the right UC-11 message.
 */
class PriceServiceException extends RuntimeException
{
    /** Service URL or API key missing in `.env`. */
    public const NOT_CONFIGURED = 'not_configured';

    /** Service is still downloading BI history or training (503 `status: syncing`); normal, retry later. */
    public const SYNCING = 'syncing';

    /** Not enough history to forecast this commodity (422, UC-11 alternative 3a). */
    public const INSUFFICIENT_DATA = 'insufficient_data';

    /** Service or Bank Indonesia unreachable, rejected, or returned an unexpected payload (UC-11 alternative 2a). */
    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
