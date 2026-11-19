<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\HargaReferensiKomoditas;
use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\PrediksiHarga;
use App\Models\Role;
use App\Models\TenantCommodity;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PricePredictionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UC-11 Pantau Tren Harga dan Rekomendasi Harga Pagu (FR-15, FR-16, BR-01, BR-02, BR-24).
 */
class AnalitikHargaTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenantA;

    private PerusahaanBuyer $tenantB;

    private Komoditas $cabai;

    private Komoditas $beras;

    private Komoditas $onlyTenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        config([
            'services.price_service.url' => 'https://price.test',
            'services.price_service.key' => 'test-key',
        ]);

        $this->tenantA = PerusahaanBuyer::factory()->create();
        $this->tenantB = PerusahaanBuyer::factory()->create();

        $this->cabai = Komoditas::factory()->create(['name' => 'Cabai Rawit', 'unit' => 'kg', 'api_reference_code' => 'com_16']);
        $this->beras = Komoditas::factory()->create(['name' => 'Beras', 'unit' => 'kg', 'api_reference_code' => 'com_3']);
        $this->onlyTenantB = Komoditas::factory()->create(['name' => 'Gula Pasir', 'api_reference_code' => 'com_21']);

        foreach ([$this->cabai, $this->beras] as $komoditas) {
            TenantCommodity::factory()->create(['tenant_id' => $this->tenantA->id, 'commodity_id' => $komoditas->id, 'is_active' => true]);
        }
        TenantCommodity::factory()->create(['tenant_id' => $this->tenantB->id, 'commodity_id' => $this->onlyTenantB->id, 'is_active' => true]);
    }

    private function tenantUser(RoleName $role, ?PerusahaanBuyer $tenant = null): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role->value)->value('id'),
            'tenant_id' => ($tenant ?? $this->tenantA)->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<float>  $prices
     * @return list<array{date: string, price: float}>
     */
    private function series(string $startDate, array $prices): array
    {
        return array_map(
            fn (int $i, float $price): array => ['date' => now()->parse($startDate)->addDays($i)->toDateString(), 'price' => $price],
            array_keys($prices),
            $prices
        );
    }

    private function fakeService(?array $forecastBody = null, int $forecastStatus = 200, array $forecastHeaders = []): void
    {
        Http::fake([
            'price.test/v1/prices*' => Http::response([
                'code' => 'com_16',
                'source' => 'bank_indonesia',
                'prices' => $this->series(now()->subDays(2)->toDateString(), [80000, 81000, 80950]),
            ]),
            'price.test/v1/forecast*' => Http::response($forecastBody ?? [
                'code' => 'com_16',
                'model' => 'linear_autoregression',
                'history_end' => now()->toDateString(),
                'syncing' => false,
                'predictions' => $this->series(now()->addDay()->toDateString(), [81000, 84000, 82500]),
            ], $forecastStatus, $forecastHeaders),
        ]);
    }

    private function storeBatch(Komoditas $komoditas, array $predicted, string $calculatedAt): void
    {
        foreach ($predicted as $i => $price) {
            PrediksiHarga::create([
                'uuid' => (string) Str::uuid(),
                'commodity_id' => $komoditas->id,
                'prediction_date' => now()->addDays($i + 1)->toDateString(),
                'predicted_price' => $price,
                'recommended_ceiling_price' => round($price * PricePredictionService::CEILING_MARGIN, 2),
                'calculated_at' => $calculatedAt,
            ]);
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/analitik-harga')->assertRedirect('/login');
        $this->post("/analitik-harga/{$this->cabai->uuid}/perbarui")->assertRedirect('/login');
    }

    /**
     * @return array<string, array{RoleName}>
     */
    public static function allowedRoles(): array
    {
        return [
            'owner' => [RoleName::Owner],
            'purchasing' => [RoleName::Purchasing],
        ];
    }

    #[DataProvider('allowedRoles')]
    public function test_owner_and_purchasing_see_latest_forecast_and_ceiling(RoleName $role): void
    {
        $this->storeBatch($this->beras, [10000, 10000], now()->subDays(2)->toDateTimeString());
        $this->storeBatch($this->beras, [15000, 16000, 15500], now()->subDay()->toDateTimeString());
        HargaReferensiKomoditas::create([
            'commodity_id' => $this->beras->id,
            'date' => now()->subDay()->toDateString(),
            'price' => 14800,
            'source' => HargaReferensiKomoditas::SOURCE_BANK_INDONESIA,
        ]);

        $this->actingAs($this->tenantUser($role))
            ->get("/analitik-harga?komoditas={$this->beras->uuid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AnalitikHarga/Index')
                ->has('commodity_options', 2)
                ->where('commodity.uuid', $this->beras->uuid)
                ->where('commodity.is_mapped', true)
                ->has('predictions', 3)
                ->has('history', 1)
                ->where('ceiling.predicted_price', 16000)
                ->where('ceiling.recommended_ceiling_price', 16800)
                ->where('latest_price.price', 14800)
                ->where('notice', null)
                ->where('can.refresh', true)
                ->missing('commodity.id')
            );
    }

    /**
     * @return array<string, array{RoleName}>
     */
    public static function forbiddenRoles(): array
    {
        return [
            'manajemen' => [RoleName::Manajemen],
            'qc_gudang' => [RoleName::QcGudang],
        ];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_tenant_roles_cannot_open_dashboard_or_refresh(RoleName $role): void
    {
        $user = $this->tenantUser($role);

        $this->actingAs($user)->get('/analitik-harga')->assertForbidden();
        $this->actingAs($user)->post("/analitik-harga/{$this->cabai->uuid}/perbarui")->assertForbidden();
    }

    public function test_vendor_and_superadmin_never_reach_ceiling_prices(): void
    {
        $vendor = Vendor::create([
            'company_name' => 'CV Tani',
            'slug' => 'cv-tani-'.Str::lower(Str::random(5)),
            'tax_number' => '01.234.567.8-999.000',
            'contact_email' => 'tani@vendor.test',
            'phone' => '081234567890',
            'address' => 'Sleman',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);
        $vendorUser = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Vendor->value)->value('id'),
            'vendor_id' => $vendor->id,
            'is_active' => true,
        ]);
        $superadmin = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Superadmin->value)->value('id'),
            'is_active' => true,
        ]);

        foreach ([$vendorUser, $superadmin] as $user) {
            $this->actingAs($user)->get('/analitik-harga')->assertForbidden();
            $this->actingAs($user)->post("/analitik-harga/{$this->cabai->uuid}/perbarui")->assertForbidden();
        }
    }

    public function test_commodity_of_another_tenant_is_not_found(): void
    {
        $owner = $this->tenantUser(RoleName::Owner);
        Http::fake();

        $this->actingAs($owner)
            ->getJson("/analitik-harga?komoditas={$this->onlyTenantB->uuid}")
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson("/analitik-harga/{$this->onlyTenantB->uuid}/perbarui")
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_invalid_commodity_parameter_is_rejected(): void
    {
        $this->actingAs($this->tenantUser(RoleName::Purchasing))
            ->getJson('/analitik-harga?komoditas=bukan-uuid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('komoditas');
    }

    public function test_refresh_backfills_prices_and_stores_ceiling_as_highest_prediction_plus_margin(): void
    {
        $this->fakeService();

        $this->actingAs($this->tenantUser(RoleName::Purchasing))
            ->post("/analitik-harga/{$this->cabai->uuid}/perbarui")
            ->assertRedirect("/analitik-harga?komoditas={$this->cabai->uuid}")
            ->assertSessionHas('success', 'Data harga dan prediksi berhasil diperbarui.');

        $this->assertSame(3, HargaReferensiKomoditas::where('commodity_id', $this->cabai->id)->where('source', 'bank_indonesia')->count());
        $this->assertSame(3, PrediksiHarga::where('commodity_id', $this->cabai->id)->count());

        $ceiling = app(PricePredictionService::class)->latestCeiling($this->cabai->id);
        $this->assertEquals(84000, (float) $ceiling->predicted_price);
        $this->assertEquals(88200, (float) $ceiling->recommended_ceiling_price);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/prices')
            && $request->hasHeader('X-API-Key', 'test-key')
            && $request['code'] === 'com_16'
            && $request['start_date'] === now()->subDays(PricePredictionService::BACKFILL_DAYS)->toDateString());
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/forecast')
            && (int) $request['horizon_days'] === PricePredictionService::HORIZON_DAYS);
    }

    public function test_second_refresh_only_requests_days_after_the_last_stored_price(): void
    {
        HargaReferensiKomoditas::create([
            'commodity_id' => $this->cabai->id,
            'date' => now()->subDays(5)->toDateString(),
            'price' => 79000,
            'source' => HargaReferensiKomoditas::SOURCE_BANK_INDONESIA,
        ]);
        $this->fakeService();

        $this->actingAs($this->tenantUser(RoleName::Owner))
            ->post("/analitik-harga/{$this->cabai->uuid}/perbarui")
            ->assertRedirect("/analitik-harga?komoditas={$this->cabai->uuid}")
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/prices')
            && $request['start_date'] === now()->subDays(4)->toDateString()
            && $request['end_date'] === now()->toDateString());
    }

    public function test_service_still_syncing_shows_progress_message_and_keeps_previous_ceiling(): void
    {
        $this->storeBatch($this->cabai, [70000], now()->subDay()->toDateTimeString());
        $this->fakeService([
            'status' => 'syncing',
            'message' => 'Sedang mengunduh riwayat harga pertama dari Bank Indonesia (±35 menit). Prediksi tersedia setelah selesai.',
            'phase' => 'syncing',
        ], 503, ['Retry-After' => '60']);
        $purchasing = $this->tenantUser(RoleName::Purchasing);

        $this->actingAs($purchasing)
            ->post("/analitik-harga/{$this->cabai->uuid}/perbarui")
            ->assertRedirect("/analitik-harga?komoditas={$this->cabai->uuid}")
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '±35 menit'));

        $this->assertSame(1, PrediksiHarga::where('commodity_id', $this->cabai->id)->count());

        $this->actingAs($purchasing)
            ->get("/analitik-harga?komoditas={$this->cabai->uuid}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('notice.type', 'info')
                ->where('ceiling.recommended_ceiling_price', 73500)
            );
    }

    public function test_unreachable_service_falls_back_to_last_local_data(): void
    {
        $this->storeBatch($this->cabai, [70000], now()->subDay()->toDateTimeString());
        Http::fake(['price.test/*' => Http::response(['detail' => 'Gagal mengakses PIHPS'], 502)]);
        $owner = $this->tenantUser(RoleName::Owner);

        $this->actingAs($owner)
            ->post("/analitik-harga/{$this->cabai->uuid}/perbarui")
            ->assertSessionHas('error', 'Data harga terbaru tidak dapat diambil. Silakan coba lagi nanti.');

        $this->actingAs($owner)
            ->get("/analitik-harga?komoditas={$this->cabai->uuid}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('notice.type', 'warning')
                ->where('notice.message', fn (string $message) => str_starts_with($message, 'Data harga terbaru tidak dapat diambil. Menampilkan data terakhir per '))
                ->where('ceiling.recommended_ceiling_price', 73500)
            );
    }

    public function test_insufficient_history_reports_uc11_alternative_3a(): void
    {
        HargaReferensiKomoditas::create([
            'commodity_id' => $this->cabai->id,
            'date' => now()->subDay()->toDateString(),
            'price' => 80000,
            'source' => HargaReferensiKomoditas::SOURCE_BANK_INDONESIA,
        ]);
        $this->fakeService(['detail' => 'Riwayat com_16 kurang dari 60 hari kerja.'], 422);
        $owner = $this->tenantUser(RoleName::Owner);

        $this->actingAs($owner)
            ->post("/analitik-harga/{$this->cabai->uuid}/perbarui")
            ->assertSessionHas('error', 'Prediksi harga untuk komoditas ini belum dapat dihitung.');

        $this->actingAs($owner)
            ->get("/analitik-harga?komoditas={$this->cabai->uuid}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('ceiling', null)
                ->where('notice.message', 'Prediksi harga untuk komoditas ini belum dapat dihitung.')
            );
    }

    public function test_commodity_without_any_data_reports_uc11_alternative_2b(): void
    {
        $this->actingAs($this->tenantUser(RoleName::Owner))
            ->get("/analitik-harga?komoditas={$this->cabai->uuid}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('history', 0)
                ->has('predictions', 0)
                ->where('notice.message', 'Data harga untuk komoditas ini belum tersedia.')
            );
    }

    public function test_sync_command_updates_every_mapped_active_commodity(): void
    {
        Komoditas::factory()->create(['api_reference_code' => null]);
        Komoditas::factory()->create(['api_reference_code' => 'com_7', 'is_active' => false]);
        $this->fakeService();

        $this->artisan('harga-pagu:sync')
            ->expectsOutputToContain('3 dari 3 komoditas diperbarui.')
            ->assertSuccessful();

        foreach ([$this->cabai, $this->beras, $this->onlyTenantB] as $komoditas) {
            $this->assertNotNull(app(PricePredictionService::class)->latestCeiling($komoditas->id));
        }
        Http::assertSentCount(6);
    }

    public function test_purchase_order_form_uses_current_ceiling_price(): void
    {
        $this->storeBatch($this->beras, [15000, 16000], now()->toDateTimeString());

        $this->actingAs($this->tenantUser(RoleName::Purchasing))
            ->get('/purchase-order/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('komoditas_options', fn ($options) => collect($options)->firstWhere('value', $this->beras->uuid)['harga_pagu'] == 16800
                    && collect($options)->firstWhere('value', $this->cabai->uuid)['harga_pagu'] === null)
            );
    }
}
