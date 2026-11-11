<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves public uploads (avatars, app logo) from the "public" disk through the application
 * domain, so images keep loading where the object storage's own public domain is blocked.
 */
class PublicFileController extends Controller
{
    /**
     * Directories on the public disk that may be served; anything else is never exposed.
     *
     * @var list<string>
     */
    private const SERVABLE_DIRECTORIES = ['avatar', 'pengaturan'];

    public function __invoke(string $path): StreamedResponse
    {
        $segments = explode('/', $path);

        abort_unless(
            count($segments) >= 2
                && in_array($segments[0], self::SERVABLE_DIRECTORIES, true)
                && ! in_array('', $segments, true)
                && ! in_array('.', $segments, true)
                && ! in_array('..', $segments, true),
            404,
        );

        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            // Stored names are random per upload, so a changed file always gets a new URL.
            'Cache-Control' => 'public, max-age=2592000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
