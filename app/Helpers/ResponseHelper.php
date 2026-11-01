<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ResponseHelper
{
    /**
     * Input that must never be flashed back to the session (also registered as `dontFlash` in bootstrap/app.php).
     *
     * @var list<string>
     */
    public const SENSITIVE_INPUT = [
        'password',
        'password_confirmation',
        'current_password',
        'password_saat_ini',
        'otp',
        'code',
        'token',
        'pg_server_key',
        'pg_webhook_token',
    ];

    /**
     * Resolve incoming request instance.
     */
    protected static function resolveRequest(?Request $request): Request
    {
        return $request ?? request();
    }

    /**
     * Normalize errors parameter into array or null.
     *
     * @param  array<string, mixed>|string|null  $errors
     * @return array<string, mixed>|null
     */
    protected static function normalizeErrors(array|string|null $errors, string $fallbackKey = 'error'): ?array
    {
        if ($errors === null) {
            return null;
        }

        if (is_string($errors)) {
            return [$fallbackKey => $errors];
        }

        return $errors;
    }

    /**
     * Build the standard JSON envelope.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $debug
     */
    protected static function json(
        bool $success,
        string $message,
        mixed $data,
        ?array $errors,
        int $status,
        ?array $debug = null
    ): JsonResponse {
        $payload = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ];

        if ($debug !== null) {
            $payload['debug'] = $debug;
        }

        return response()->json($payload, $status);
    }

    /**
     * Redirect back with errors and the request input, minus sensitive fields.
     *
     * @param  array<string, mixed>  $errors
     */
    protected static function back(Request $request, array $errors, ?string $flashError = null): RedirectResponse
    {
        $redirect = redirect()->back()
            ->withErrors($errors)
            ->withInput($request->except(self::SENSITIVE_INPUT));

        return $flashError !== null ? $redirect->with('error', $flashError) : $redirect;
    }

    /**
     * Shared handler for 4xx failures.
     *
     * @param  array<string, mixed>|string|null  $errors
     */
    protected static function failure(
        int $status,
        string $message,
        array|string|null $errors,
        string $fallbackKey,
        ?Request $request,
        bool $flashMessage = true
    ): JsonResponse|RedirectResponse {
        $req = static::resolveRequest($request);
        $normalizedErrors = static::normalizeErrors($errors, $fallbackKey) ?? [$fallbackKey => $message];

        if ($req->expectsJson()) {
            return static::json(false, $message, null, $normalizedErrors, $status);
        }

        return static::back($req, $normalizedErrors, $flashMessage ? $message : null);
    }

    /**
     * Shared handler for 2xx success responses.
     */
    protected static function successful(
        int $status,
        string $message,
        mixed $data,
        ?string $redirectTo,
        ?Request $request
    ): JsonResponse|RedirectResponse {
        $req = static::resolveRequest($request);

        if ($req->expectsJson()) {
            return static::json(true, $message, $data, null, $status);
        }

        $redirect = $redirectTo
            ? redirect()->to($redirectTo)
            : redirect()->intended(config('fortify.home', '/dashboard'));

        return $redirect->with('success', $message)->with('status', $message);
    }

    /**
     * Return a 200 Success response.
     *
     * @param  array<string, mixed>|object|null  $data
     */
    public static function success(
        string $message = 'Operasi berhasil.',
        mixed $data = null,
        ?string $redirectTo = null,
        ?Request $request = null
    ): JsonResponse|RedirectResponse {
        return static::successful(200, $message, $data, $redirectTo, $request);
    }

    /**
     * Return a 201 Created response.
     *
     * @param  array<string, mixed>|object|null  $data
     */
    public static function created(
        string $message = 'Data berhasil dibuat.',
        mixed $data = null,
        ?string $redirectTo = null,
        ?Request $request = null
    ): JsonResponse|RedirectResponse {
        return static::successful(201, $message, $data, $redirectTo, $request);
    }

    /**
     * Return a 400 Bad Request response.
     *
     * @param  array<string, mixed>|string|null  $errors
     */
    public static function badRequest(
        string $message = 'Permintaan tidak valid.',
        array|string|null $errors = null,
        ?Request $request = null
    ): JsonResponse|RedirectResponse {
        return static::failure(400, $message, $errors, 'error', $request);
    }

    /**
     * Return a 401 Unauthorized response.
     *
     * @param  array<string, mixed>|string|null  $errors
     */
    public static function unauthorized(
        string $message = 'Autentikasi gagal.',
        array|string|null $errors = null,
        ?Request $request = null
    ): JsonResponse|RedirectResponse {
        return static::failure(401, $message, $errors, 'email', $request);
    }

    /**
     * Return a 403 Forbidden response.
     *
     * @param  array<string, mixed>|string|null  $errors
     */
    public static function forbidden(
        string $message = 'Akses ditolak.',
        array|string|null $errors = null,
        ?Request $request = null
    ): JsonResponse|RedirectResponse {
        return static::failure(403, $message, $errors, 'error', $request);
    }

    /**
     * Return a 404 Not Found response.
     *
     * @param  array<string, mixed>|string|null  $errors
     */
    public static function notFound(
        string $message = 'Data tidak ditemukan.',
        array|string|null $errors = null,
        ?Request $request = null
    ): JsonResponse|RedirectResponse {
        return static::failure(404, $message, $errors, 'error', $request);
    }

    /**
     * Return a 422 Validation Error response.
     *
     * @param  array<string, mixed>|string  $errors
     */
    public static function validationError(
        array|string $errors,
        string $message = 'Terjadi kesalahan validasi data.',
        ?Request $request = null
    ): JsonResponse|RedirectResponse {
        return static::failure(422, $message, $errors, 'error', $request, flashMessage: false);
    }

    /**
     * Return a 500 Server Error response.
     *
     * The exception is reported through the exception handler (structured log with request context).
     * Users only see a generic message; exception details are added to JSON responses when APP_DEBUG is on.
     */
    public static function serverError(
        string $message = 'Terjadi kesalahan pada sistem server.',
        ?Throwable $exception = null,
        ?Request $request = null
    ): JsonResponse|RedirectResponse {
        $req = static::resolveRequest($request);

        if ($exception !== null) {
            report($exception);
        }

        $publicMessage = 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.';

        if ($req->expectsJson()) {
            return static::json(false, $publicMessage, null, null, 500, static::debugDetails($message, $exception));
        }

        return static::back($req, ['general' => $publicMessage], $publicMessage);
    }

    /**
     * Exception details for developers; never returned when APP_DEBUG is off.
     *
     * @return array<string, mixed>|null
     */
    protected static function debugDetails(string $context, ?Throwable $exception): ?array
    {
        if (! config('app.debug') || $exception === null) {
            return null;
        }

        return [
            'context' => $context,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'location' => $exception->getFile().':'.$exception->getLine(),
        ];
    }
}
