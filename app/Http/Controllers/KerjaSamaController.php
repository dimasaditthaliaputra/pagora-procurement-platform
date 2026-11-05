<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\KerjaSama\IndexCalonMitraRequest;
use App\Http\Resources\CalonMitraResource;
use App\Models\User;
use App\Services\CalonMitraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;
use App\Services\VendorInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class KerjaSamaController extends Controller
{
    public function __construct(
        private readonly CalonMitraService $service
    ) {}

    /**
     * Display the list of potential partner companies for the authenticated Vendor.
     */
    public function calonMitraIndex(IndexCalonMitraRequest $request): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $filters = $request->validated();
            $perPage = (int) ($filters['per_page'] ?? 12);

            $tenants = $this->service->paginate(
                $user->vendor_id,
                $filters,
                $perPage
            );

            return Inertia::render('KerjaSama/CalonMitra/Index', [
                'tenants' => CalonMitraResource::collection($tenants),
                'filters' => $request->only(
                    'search',
                    'sort',
                    'direction',
                    'per_page',
                    'lokasi'
                ),
            ]);
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal memuat daftar calon mitra.',
                $e
            );
        }
    }

    /**
     * Display the detail of an eligible Buyer company.
     */
    public function calonMitraShow(string $uuid): Response|JsonResponse
    {
        try {
            $buyer = $this->service->findEligibleBuyer($uuid);

            if (! $buyer) {
                abort(404);
            }

            return Inertia::render('KerjaSama/CalonMitra/Show', [
                'buyer' => [
                    'uuid' => $buyer->uuid,
                    'company_name' => $buyer->company_name,
                    'address' => $buyer->address,
                    'verification_status' => $buyer->verification_status->value,
                    'is_active' => $buyer->is_active,
                    'verified_at' => $buyer->verified_at,
                ],
            ]);
        } catch (Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            return ResponseHelper::serverError(
                'Gagal memuat detail calon mitra.',
                $e
            );
        }
    }

    /**
     * Submit a partnership request from the authenticated Vendor.
     */
    public function ajukanKerjaSama(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isVendor() || ! $user->vendor_id) {
            abort(403, 'Hanya Vendor yang dapat mengajukan kerja sama.');
        }

        try {
            $this->service->submitPartnership(
                $user->vendor_id,
                $uuid
            );

            return redirect()
                ->route('kerja-sama.calon-mitra.index')
                ->with('success', 'Pengajuan kerja sama berhasil dikirim.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return ResponseHelper::serverError(
                'Gagal mengajukan kerja sama.',
                $e
            );
        }
    }

    public function incomingInvitations(
    Request $request,
    VendorInvitationService $service
    ) {
        Gate::authorize('viewAny', \App\Models\KerjaSama::class);

        $vendorId = (int) $request->user()->vendor_id;

        return inertia('KerjaSama/UndanganMasuk/Index', [
            'invitations' => $service->paginateIncoming($vendorId),
        ]);
    }

    public function showInvitation(
        Request $request,
        string $uuid,
        VendorInvitationService $service
    ) {
        $invitation = $service->findIncoming(
            $uuid,
            (int) $request->user()->vendor_id,
            $request->user()
        );

        abort_unless($invitation, 404);

        return inertia('KerjaSama/UndanganMasuk/Show', [
            'invitation' => $invitation,
        ]);
    }

    public function respondInvitation(
        Request $request,
        string $uuid,
        VendorInvitationService $service
    ) {
        $validated = $request->validate([
            'action' => ['required', 'in:accept,reject'],
            'rejection_reason' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:action,reject',
            ],
        ], [
            'action.required' => 'Tindakan wajib dipilih.',
            'action.in' => 'Tindakan tidak valid.',
            'rejection_reason.required_if' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 1000 karakter.',
        ]);

        $service->respond(
            $request->user(),
            $uuid,
            $validated['action'],
            $validated['rejection_reason'] ?? null
        );

        return redirect()
            ->route('kerja-sama.undangan-masuk.index')
            ->with('success', $validated['action'] === 'accept'
                ? 'Undangan kerja sama berhasil diterima.'
                : 'Undangan kerja sama berhasil ditolak.');
    }
}
