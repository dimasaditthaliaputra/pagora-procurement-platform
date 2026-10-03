<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return inertia('Home', [
        'message' => 'Selamat datang di PAGORA — Ekosistem Pengadaan Bahan Baku B2B.',
    ]);
})->name('home');

Route::get('/login', function () {
    return inertia('Auth/Login');
})->name('login');

Route::get('/register-vendor', function () {
    return inertia('Auth/RegisterVendor');
})->name('register.vendor');
