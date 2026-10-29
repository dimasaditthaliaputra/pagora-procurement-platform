<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\DokumenLegalitasVendorRepositoryInterface;
use App\Repositories\Contracts\KomoditasRepositoryInterface;
use App\Repositories\Contracts\PengaturanRepositoryInterface;
use App\Repositories\Contracts\PerusahaanBuyerRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Repositories\Eloquent\DokumenLegalitasVendorRepository;
use App\Repositories\Eloquent\KomoditasRepository;
use App\Repositories\Eloquent\PengaturanRepository;
use App\Repositories\Eloquent\PerusahaanBuyerRepository;
use App\Repositories\Eloquent\RoleRepository;
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
            PerusahaanBuyerRepositoryInterface::class,
            PerusahaanBuyerRepository::class
        );

        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
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
