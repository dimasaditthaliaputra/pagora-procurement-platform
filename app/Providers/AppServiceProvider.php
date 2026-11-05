<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Invoice;
use App\Models\KerjaSama;
use App\Models\PenerimaanBarang;
use App\Models\Pengiriman;
use App\Policies\InvoicePolicy;
use App\Policies\KerjaSamaPolicy;
use App\Policies\PenerimaanBarangPolicy;
use App\Policies\PengirimanPolicy;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\KerjaSamaRepositoryInterface;
use App\Repositories\Eloquent\InvoiceRepository;
use App\Repositories\Eloquent\KerjaSamaRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            KerjaSamaRepositoryInterface::class,
            KerjaSamaRepository::class
        );

        $this->app->bind(
            InvoiceRepositoryInterface::class,
            InvoiceRepository::class
        );
    }

    public function boot(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());

        Gate::policy(KerjaSama::class, KerjaSamaPolicy::class);
        Gate::policy(Pengiriman::class, PengirimanPolicy::class);
        Gate::policy(PenerimaanBarang::class, PenerimaanBarangPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        $this->configureDevelopmentDiagnostics();
        $this->configureSlowQueryLogging();
    }

    private function configureDevelopmentDiagnostics(): void
    {
        if (! $this->app->isLocal()) {
            return;
        }

        Model::preventLazyLoading();

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            Log::channel('single')->debug('Lazy loading terdeteksi (potensi N+1).', [
                'model' => $model::class,
                'relation' => $relation,
            ]);
        });
    }

    private function configureSlowQueryLogging(): void
    {
        $thresholdMs = (int) config('database.slow_query_ms', 0);

        if ($thresholdMs <= 0) {
            return;
        }

        DB::listen(function (QueryExecuted $query) use ($thresholdMs): void {
            if ($query->time < $thresholdMs) {
                return;
            }

            Log::warning('Query lambat terdeteksi.', [
                'connection' => $query->connectionName,
                'time_ms' => $query->time,
                'sql' => $query->sql,
            ]);
        });
    }
}
