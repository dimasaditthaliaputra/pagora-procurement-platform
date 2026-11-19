<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * Display the authenticated user dashboard.
     */
    public function index(Request $request): Response
    {
        return Inertia::render(
            'Dashboard',
            $this->dashboardService->buildForUser($request->user())
        );
    }
}
