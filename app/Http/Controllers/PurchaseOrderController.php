<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Frontend prototype only: pages read mock data from resources/js/Pages/PurchaseOrder/mock-data.js.
 * Replace each render with real props (via PurchaseOrderService + Resources) when the backend is built.
 */
class PurchaseOrderController extends Controller
{
    /**
     * Display the purchase order list.
     */
    public function index(): Response
    {
        return Inertia::render('PurchaseOrder/Index');
    }

    /**
     * Show the form for creating a purchase order.
     */
    public function create(): Response
    {
        return Inertia::render('PurchaseOrder/Create');
    }

    /**
     * Display a purchase order.
     */
    public function show(string $uuid): Response
    {
        return Inertia::render('PurchaseOrder/Show', ['uuid' => $uuid]);
    }

    /**
     * Show the form for editing a draft purchase order.
     */
    public function edit(string $uuid): Response
    {
        return Inertia::render('PurchaseOrder/Edit', ['uuid' => $uuid]);
    }

    /**
     * Display the negotiation chat of a purchase order.
     */
    public function negosiasi(string $uuid): Response
    {
        return Inertia::render('PurchaseOrder/Negosiasi', ['uuid' => $uuid]);
    }
}
