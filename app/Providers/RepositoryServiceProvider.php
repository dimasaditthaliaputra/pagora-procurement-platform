<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\CommodityCategoryRepositoryInterface;
use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use App\Repositories\Contracts\KomoditasRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\PenerimaanBarangRepositoryInterface;
use App\Repositories\Contracts\PengaturanRepositoryInterface;
use App\Repositories\Contracts\PengirimanRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\SengketaRepositoryInterface;
use App\Repositories\Contracts\UndanganVendorRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Repositories\Eloquent\CommodityCategoryRepository;
use App\Repositories\Eloquent\DokumenLegalitasVendorRepository;
use App\Repositories\Eloquent\KomoditasRepository;
use App\Repositories\Eloquent\NotificationRepository;
use App\Repositories\Eloquent\PenerimaanBarangRepository;
use App\Repositories\Eloquent\PengaturanRepository;
use App\Repositories\Eloquent\PengirimanRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\PerusahaanBuyerRepository;
use App\Repositories\Eloquent\PurchaseOrderRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\SengketaRepository;
use App\Repositories\Eloquent\UndanganVendorRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\VendorRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );

        $this->app->bind(
            KomoditasRepositoryInterface::class,
            KomoditasRepository::class
        );

        $this->app->bind(
            CommodityCategoryRepositoryInterface::class,
            CommodityCategoryRepository::class
        );

        $this->app->bind(
            VendorRepositoryInterface::class,
            VendorRepository::class
        );

        $this->app->bind(
            DokumenLegalitasVendorRepositoryInterface::class,
            DokumenLegalitasVendorRepository::class
        );

        $this->app->bind(
            PengaturanRepositoryInterface::class,
            PengaturanRepository::class
        );

        $this->app->bind(
            UndanganVendorRepositoryInterface::class,
            UndanganVendorRepository::class
        );

        $this->app->bind(
            PerusahaanBuyerRepositoryInterface::class,
            PerusahaanBuyerRepository::class
        );

        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );

        $this->app->bind(
            PermissionRepositoryInterface::class,
            PermissionRepository::class
        );

        $this->app->bind(
            PurchaseOrderRepositoryInterface::class,
            PurchaseOrderRepository::class
        );

        $this->app->bind(
            PengirimanRepositoryInterface::class,
            PengirimanRepository::class
        );

        $this->app->bind(
            PenerimaanBarangRepositoryInterface::class,
            PenerimaanBarangRepository::class
        );

        $this->app->bind(
            SengketaRepositoryInterface::class,
            SengketaRepository::class
        );

        $this->app->bind(
            NotificationRepositoryInterface::class,
            NotificationRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
