<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KomoditasController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\VendorRegistrationController;
use App\Http\Controllers\VerifikasiDokumenController;
use Illuminate\Support\Facades\Route;

// Public Home
Route::get('/', [HomeController::class, 'index'])->name('home');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'storeAuth'])->name('login.store');

    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'storeRegister'])->name('register.store');

    // Vendor Candidate Registration
    Route::prefix('register/vendor')->name('register.vendor')->group(function () {
        Route::get('/', [VendorRegistrationController::class, 'create']);
        Route::post('/', [VendorRegistrationController::class, 'store'])->name('.store');
    });

    Route::redirect('/register-vendor', '/register/vendor');

    Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Master Komoditas Routes
    Route::controller(KomoditasController::class)
        ->prefix('master/komoditas')
        ->name('master.komoditas.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{uuid}/edit', 'edit')->whereUuid('uuid')->name('edit');
            Route::put('/{uuid}', 'update')->whereUuid('uuid')->name('update');
            Route::delete('/{uuid}', 'destroy')->whereUuid('uuid')->name('destroy');
        });

    // Manajemen Pengguna (Internal Perusahaan) Routes
    Route::controller(PegawaiController::class)
        ->prefix('manajemen/pengguna')
        ->name('manajemen.pengguna.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{uuid}', 'show')->whereUuid('uuid')->name('show');
            Route::put('/{uuid}', 'update')->whereUuid('uuid')->name('update');
            Route::delete('/{uuid}', 'destroy')->whereUuid('uuid')->name('destroy');
        });

    Route::redirect('/manajemen/internal-perusahaan', '/manajemen/pengguna');

    // Vendor Legal Document Verification Routes (Superadmin Only)
    Route::middleware('superadmin')
        ->prefix('superadmin/verifikasi-dokumen')
        ->name('verifikasi-dokumen.')
        ->controller(VerifikasiDokumenController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');

            Route::post('/{uuid}/verifikasi', 'verify')
                ->whereUuid('uuid')
                ->name('verify');

            Route::get('/{uuid}/file', 'download')
                ->whereUuid('uuid')
                ->name('download');
        });
});
