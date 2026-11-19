<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\PurchaseOrder\KeluarkanItemRequest;
use App\Http\Requests\PurchaseOrder\PollChatNegosiasiRequest;
use App\Http\Requests\PurchaseOrder\StoreChatNegosiasiRequest;
use App\Http\Requests\PurchaseOrder\TawarItemRequest;
use App\Http\Resources\ChatNegosiasiResource;
use App\Http\Resources\NegotiationStateResource;
use App\Services\NegotiationService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Smart Negotiation Chat (UC-12, UC-13, FR-19..21). New messages reach the other party by polling (NFR-12).
 */
class NegotiationController extends Controller
{
    public function __construct(
        protected NegotiationService $service
    ) {}

    public function show(Request $request, string $uuid): Response|JsonResponse|RedirectResponse
    {
        try {
            $room = $this->service->room($request->user(), $uuid);

            return Inertia::render('PurchaseOrder/Negosiasi', [
                'negotiation' => (new NegotiationStateResource($room['purchase_order'], $room['side'], $room['ceilings']))->resolve(),
                'messages' => ChatNegosiasiResource::collection($room['messages'])->resolve(),
                'poll_interval_ms' => NegotiationService::POLL_INTERVAL_MS,
            ]);
        } catch (ModelNotFoundException) {
            // Like the PO detail page: a page visit gets a real 404/403 page instead of a redirect back.
            if ($request->expectsJson()) {
                return ResponseHelper::notFound('Purchase Order tidak ditemukan.');
            }
            abort(404, 'Purchase Order tidak ditemukan.');
        } catch (AuthorizationException) {
            if ($request->expectsJson()) {
                return ResponseHelper::forbidden('Anda tidak memiliki akses ke ruang negosiasi ini.');
            }
            abort(403, 'Anda tidak memiliki akses ke ruang negosiasi ini.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat ruang negosiasi.', $e);
        }
    }

    /**
     * JSON polling endpoint: messages after the client's cursor plus the current item state.
     */
    public function poll(PollChatNegosiasiRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        try {
            $updates = $this->service->updates($request->user(), $uuid, $request->validated('after'));

            return ResponseHelper::success('Pembaruan negosiasi.', [
                'negotiation' => (new NegotiationStateResource($updates['purchase_order'], $updates['side']))->resolve(),
                'messages' => ChatNegosiasiResource::collection($updates['messages'])->resolve(),
            ]);
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Purchase Order tidak ditemukan.');
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses ke ruang negosiasi ini.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memuat pembaruan negosiasi.', $e);
        }
    }

    /**
     * Sent as JSON by the chat composer (no page visit); a plain form post still redirects back.
     */
    public function storeMessage(StoreChatNegosiasiRequest $request, string $uuid): JsonResponse|RedirectResponse
    {
        return $this->act($uuid, 'Pesan terkirim.', fn () => (new ChatNegosiasiResource($this->service->sendMessage(
            $request->user(),
            $uuid,
            trim((string) $request->validated('message')),
        )))->resolve($request));
    }

    public function offer(TawarItemRequest $request, string $uuid, string $itemUuid): JsonResponse|RedirectResponse
    {
        $note = trim((string) $request->validated('message'));

        return $this->act($uuid, 'Tawaran harga terkirim.', fn () => $this->service->offer(
            $request->user(),
            $uuid,
            $itemUuid,
            (float) $request->validated('offer_amount'),
            $note !== '' ? $note : null,
        ));
    }

    public function accept(Request $request, string $uuid, string $itemUuid): JsonResponse|RedirectResponse
    {
        return $this->act($uuid, 'Harga item disepakati.', fn () => $this->service->accept($request->user(), $uuid, $itemUuid));
    }

    public function removeItem(KeluarkanItemRequest $request, string $uuid, string $itemUuid): JsonResponse|RedirectResponse
    {
        $reason = trim((string) $request->validated('reason'));

        return $this->act($uuid, 'Item dikeluarkan dari Purchase Order.', fn () => $this->service->removeItem(
            $request->user(),
            $uuid,
            $itemUuid,
            $reason !== '' ? $reason : null,
        ));
    }

    /**
     * Run a negotiation action and answer with the shared response conventions (RULES §6).
     * Whatever the closure returns becomes the JSON `data`.
     */
    private function act(string $uuid, string $successMessage, Closure $action): JsonResponse|RedirectResponse
    {
        try {
            $data = $action();

            return ResponseHelper::success($successMessage, $data, route('purchase-order.negosiasi', $uuid));
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Purchase Order atau item tidak ditemukan.');
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak dapat melakukan aksi ini pada negosiasi.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memproses aksi negosiasi.', $e);
        }
    }
}
