<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResponseHelper
{
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
        $req = static::resolveRequest($request);

        if ($req->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
                'errors' => null,
            ], 200);
        }

        $redirect = $redirectTo
            ? redirect()->to($redirectTo)
            : redirect()->intended(config('fortify.home', '/dashboard'));

        return $redirect->with('success', $message)->with('status', $message);
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
        $req = static::resolveRequest($request);

        if ($req->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
                'errors' => null,
            ], 201);
        }

        $redirect = $redirectTo
            ? redirect()->to($redirectTo)
            : redirect()->intended(config('fortify.home', '/dashboard'));

        return $redirect->with('success', $message)->with('status', $message);
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
        $req = static::resolveRequest($request);
        $normalizedErrors = static::normalizeErrors($errors, 'error') ?? ['error' => $message];

        if ($req->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => $normalizedErrors,
            ], 400);
        }

        return redirect()->back()->with('error', $message)->withErrors($normalizedErrors)->withInput();
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
        $req = static::resolveRequest($request);
        $normalizedErrors = static::normalizeErrors($errors, 'email') ?? ['email' => $message];

        if ($req->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => $normalizedErrors,
            ], 401);
        }

        return redirect()->back()->with('error', $message)->withErrors($normalizedErrors)->withInput();
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
        $req = static::resolveRequest($request);
        $normalizedErrors = static::normalizeErrors($errors, 'error') ?? ['error' => $message];

        if ($req->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => $normalizedErrors,
            ], 403);
        }

        return redirect()->back()->with('error', $message)->withErrors($normalizedErrors)->withInput();
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
        $req = static::resolveRequest($request);
        $normalizedErrors = static::normalizeErrors($errors, 'error') ?? ['error' => $message];

        if ($req->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => $normalizedErrors,
            ], 404);
        }

        return redirect()->back()->with('error', $message)->withErrors($normalizedErrors)->withInput();
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
        $req = static::resolveRequest($request);
        $normalizedErrors = static::normalizeErrors($errors, 'error') ?? ['error' => $message];

        if ($req->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => $normalizedErrors,
            ], 422);
        }

        return redirect()->back()->withErrors($normalizedErrors)->withInput();
    }

    /**
     * Return a 500 Server Error response.
     */
    public static function serverError(
        string $message = 'Terjadi kesalahan pada sistem server.',
        ?Throwable $exception = null,
        ?Request $request = null
    ): JsonResponse|RedirectResponse {
        $req = static::resolveRequest($request);

        if ($exception !== null) {
            Log::error($message, [
                'exception_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        $publicMessage = 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.';

        if ($req->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $publicMessage,
                'data' => null,
                'errors' => null,
            ], 500);
        }

        return redirect()->back()->withErrors(['general' => $publicMessage])->withInput();
    }
}
