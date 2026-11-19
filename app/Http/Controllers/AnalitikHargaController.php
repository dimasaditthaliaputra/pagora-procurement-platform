<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\AnalitikHarga\IndexAnalitikHargaRequest;
use App\Http\Requests\AnalitikHarga\RefreshAnalitikHargaRequest;
use App\Http\Resources\HargaReferensiKomoditasResource;
use App\Http\Resources\PrediksiHargaResource;
use App\Models\PrediksiHarga;
use App\Services\PricePredictionService;
use App\Services\PriceServiceException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Dashboard Analitik: price trend & Harga Pagu (UC-11).
 */
class AnalitikHargaController extends Controller
{
    public function __construct(
        protected PricePredictionService $service
    ) {}

    public function index(IndexAnalitikHargaRequest $request): Response|JsonResponse|RedirectResponse
    {
        try {
            $data = $this->service->dashboard($request->user(), $request->validated('komoditas'));
            $commodity = $data['commodity'];

            return Inertia::render('AnalitikHarga/Index', [
                'commodity_options' => $data['commodity_options'],
                'commodity' => $commodity ? [
                    'uuid' => $commodity->uuid,
                    'name' => $commodity->name,
                    'unit' => $commodity->unit,
                    'is_mapped' => filled($commodity->api_reference_code),
                ] : null,
                'history' => HargaReferensiKomoditasResource::collection($data['history'])->resolve(),
                'predictions' => PrediksiHargaResource::collection($data['predictions'])->resolve(),
                'ceiling' => $data['ceiling'] ? (new PrediksiHargaResource($data['ceiling']))->resolve() : null,
                'latest_price' => $data['latest_price'] ? (new HargaReferensiKomoditasResource($data['latest_price']))->resolve() : null,
                'notice' => $data['notice'],
                'ceiling_margin_percent' => round((PricePredictionService::CEILING_MARGIN - 1) * 100, 2),
                'horizon_days' => PricePredictionService::HORIZON_DAYS,
                'can' => [
                    'refresh' => $request->user()->can('refresh', PrediksiHarga::class),
                ],
            ]);
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Komoditas tidak ditemukan.');
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk aksi ini.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat dasbor analitik harga.', $e);
        }
    }

    public function refresh(RefreshAnalitikHargaRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->service->refresh($request->user(), $uuid);

            return match ($result['status']) {
                PricePredictionService::STATUS_UPDATED, PriceServiceException::SYNCING => ResponseHelper::success(
                    $result['message'],
                    redirectTo: route('analitik-harga.index', ['komoditas' => $uuid]),
                ),
                default => ResponseHelper::badRequest($result['message']),
            };
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Komoditas tidak ditemukan.');
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk aksi ini.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui data harga.', $e);
        }
    }
}
