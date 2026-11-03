<?php

namespace App\Providers;

use App\Repositories\Contracts\KerjaSamaRepositoryInterface;
use App\Repositories\Eloquent\KerjaSamaRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            KerjaSamaRepositoryInterface::class,
            KerjaSamaRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Block migrate:fresh, db:wipe, migrate:reset, etc. on production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        $this->configureDevelopmentDiagnostics();
        $this->configureSlowQueryLogging();
    }

    /**
     * Local only: log (not throw) N+1 lazy loading so existing pages keep working while it is fixed.
     */
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

    /**
     * Log queries slower than `database.slow_query_ms` (all environments; set to 0 to disable).
     */
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
                // Bindings are omitted: they may contain personal data or credentials.
                'sql' => $query->sql,
            ]);
        });
    }
}
