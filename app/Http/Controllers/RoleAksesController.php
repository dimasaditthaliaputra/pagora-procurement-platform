<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\RoleAkses\UpdateRoleAksesRequest;
use App\Http\Resources\RoleResource;
use App\Services\RoleAksesService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RoleAksesController extends Controller
{
    public function __construct(
        protected readonly RoleAksesService $service
    ) {}

    /**
     * Display the six roles and their permission matrix.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $roles = RoleResource::collection($this->service->getRoles());
        $permissionGroups = $this->service->getPermissionGroups();

        if ($request->expectsJson()) {
            return ResponseHelper::success('Daftar role dan hak akses berhasil diambil.', [
                'roles' => $roles,
                'permission_groups' => $permissionGroups,
            ]);
        }

        return Inertia::render('Manajemen/RoleAkses/Index', [
            'roles' => $roles,
            'permission_groups' => $permissionGroups,
        ]);
    }

    /**
     * Update the description and permissions of a role.
     */
    public function update(UpdateRoleAksesRequest $request, string $role): JsonResponse|RedirectResponse
    {
        try {
            $updated = $this->service->updateAccess(
                $request->user(),
                $role,
                $request->validated('description'),
                $request->validated('permissions', [])
            );

            return ResponseHelper::success(
                "Hak akses role {$updated->roleName()?->label()} berhasil diperbarui.",
                new RoleResource($updated),
                route('manajemen.role-akses.index')
            );
        } catch (AuthorizationException) {
            return ResponseHelper::forbidden('Anda tidak memiliki akses untuk mengubah hak akses role.');
        } catch (ModelNotFoundException) {
            return ResponseHelper::notFound('Role tidak ditemukan.');
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors(), 'Gagal memperbarui hak akses role.');
        } catch (Throwable $e) {
            return ResponseHelper::serverError('Gagal memperbarui hak akses role.', $e);
        }
    }
}
