<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Display the home landing page.
     */
    public function index(): Response
    {
        return Inertia::render('Home', [
            'message' => 'Selamat datang di PAGORA — Ekosistem Pengadaan Bahan Baku B2B.',
        ]);
    }
}
