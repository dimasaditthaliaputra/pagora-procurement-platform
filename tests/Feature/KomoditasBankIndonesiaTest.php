<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\CommodityCategory;
use App\Models\Komoditas;
use App\Models\PerusahaanBuyer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CommodityCategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Master Komoditas ↔ PIHPS Bank Indonesia mapping: dropdown options, validation, and import.
 */
class KomoditasBankIndonesiaTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, CommodityCategorySeeder::class]);

        config([
            'services.price_service.url' => 'https://price.test',
            'services.price_service.key' => 'test-key',
        ]);

        $this->superadmin = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Superadmin->value)->value('id'),
            'is_active' => true,
        ]);
    }

    private function fakeCommodities(): void
    {
        Http::fake(['price.test/v1/commodities' => Http::response([
            ['code' => 'com_1', 'name' => 'Beras Kualitas Bawah I', 'category' => 'Beras', 'unit' => 'kg'],
            ['code' => 'com_11', 'name' => 'Bawang Merah Ukuran Sedang', 'category' => 'Bawang Merah', 'unit' => 'kg'],
            ['code' => 'com_14', 'name' => 'Cabai Merah Keriting', 'category' => 'Cabai Merah', 'unit' => 'kg'],
            ['code' => 'com_99', 'name' => 'Jagung Pipil', 'category' => 'Jagung', 'unit' => 'kg'],
        ])]);
    }

    public function test_superadmin_imports_unmapped_bank_indonesia_commodities_with_mapped_categories(): void
    {
        $this->fakeCommodities();

        $this->actingAs($this->superadmin)
            ->post('/master/komoditas/impor-bi')
            ->assertRedirect('/master/komoditas')
            ->assertSessionHas('success', '4 komoditas Bank Indonesia berhasil diimpor.');

        $imported = Komoditas::with('category')->get()->keyBy('api_reference_code');

        $this->assertCount(4, $imported);
        $this->assertSame('Beras Kualitas Bawah I', $imported['com_1']->name);
        $this->assertSame('Pangan Pokok', $imported['com_1']->category->name);
        $this->assertSame('Bumbu & Rempah', $imported['com_14']->category->name);
        $this->assertSame('Lainnya', $imported['com_99']->category->name);
        $this->assertSame('kg', $imported['com_11']->unit);
        $this->assertTrue($imported['com_11']->is_active);
    }

    public function test_import_skips_commodities_that_are_already_mapped_or_share_a_name(): void
    {
        $this->fakeCommodities();
        Komoditas::factory()->create(['name' => 'Beras Premium Toko', 'api_reference_code' => 'com_1']);
        Komoditas::factory()->create(['name' => 'bawang merah ukuran sedang', 'api_reference_code' => null]);

        $this->actingAs($this->superadmin)
            ->post('/master/komoditas/impor-bi')
            ->assertSessionHas('success', '2 komoditas Bank Indonesia berhasil diimpor. 2 komoditas dilewati karena sudah terdaftar.');

        $this->assertSame(4, Komoditas::count());

        $this->actingAs($this->superadmin)
            ->post('/master/komoditas/impor-bi')
            ->assertSessionHas('success', 'Semua komoditas Bank Indonesia sudah terdaftar.');

        $this->assertSame(4, Komoditas::count());
    }

    public function test_tenant_users_cannot_import(): void
    {
        $owner = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Owner->value)->value('id'),
            'tenant_id' => PerusahaanBuyer::factory()->create()->id,
            'is_active' => true,
        ]);
        Http::fake();

        $this->actingAs($owner)->post('/master/komoditas/impor-bi')->assertForbidden();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'price.test'));
        $this->assertSame(0, Komoditas::count());
    }

    public function test_import_reports_error_when_price_service_is_down(): void
    {
        Http::fake(['price.test/*' => Http::response(['detail' => 'Gagal mengakses PIHPS'], 502)]);

        $this->actingAs($this->superadmin)
            ->post('/master/komoditas/impor-bi')
            ->assertSessionHas('error', 'Daftar komoditas Bank Indonesia tidak dapat diambil. Silakan coba lagi nanti.');

        $this->assertSame(0, Komoditas::count());
    }

    public function test_form_lists_bank_indonesia_commodities(): void
    {
        $this->fakeCommodities();

        $this->actingAs($this->superadmin)
            ->get('/master/komoditas/create')
            ->assertInertia(fn (Assert $page) => $page
                ->where('bi_commodities.available', true)
                ->has('bi_commodities.options', 4)
                ->where('bi_commodities.options.1', ['value' => 'com_11', 'label' => 'Bawang Merah Ukuran Sedang (com_11)'])
            );
    }

    public function test_form_still_works_without_the_list_when_service_is_down(): void
    {
        Http::fake(['price.test/*' => Http::response([], 503)]);
        $category = CommodityCategory::where('name', 'Bumbu & Rempah')->firstOrFail();

        $this->actingAs($this->superadmin)
            ->get('/master/komoditas/create')
            ->assertInertia(fn (Assert $page) => $page->where('bi_commodities', ['available' => false, 'options' => []]));

        $this->actingAs($this->superadmin)
            ->post('/master/komoditas', ['name' => 'Bawang Merah Brebes', 'unit' => 'kg', 'commodity_category_uuid' => $category->uuid, 'api_reference_code' => 'com_11'])
            ->assertRedirect('/master/komoditas');
    }

    public function test_mapping_must_be_a_listed_code_and_unique(): void
    {
        $this->fakeCommodities();
        $category = CommodityCategory::where('name', 'Bumbu & Rempah')->firstOrFail();
        $mapped = Komoditas::factory()->create(['name' => 'Cabai Keriting', 'api_reference_code' => 'com_14']);
        $payload = ['name' => 'Bawang Merah Brebes', 'unit' => 'kg', 'commodity_category_uuid' => $category->uuid];

        foreach ([
            ['101', 'Kode komoditas Bank Indonesia tidak valid.'],
            ['com_500', 'Komoditas Bank Indonesia yang dipilih tidak ditemukan.'],
            ['com_14', 'Komoditas Bank Indonesia ini sudah dipetakan ke komoditas lain.'],
        ] as [$code, $message]) {
            $this->actingAs($this->superadmin)
                ->post('/master/komoditas', [...$payload, 'api_reference_code' => $code])
                ->assertSessionHasErrors(['api_reference_code' => $message]);
        }

        $this->actingAs($this->superadmin)
            ->post('/master/komoditas', [...$payload, 'api_reference_code' => 'com_11'])
            ->assertRedirect('/master/komoditas');

        $this->actingAs($this->superadmin)
            ->put("/master/komoditas/{$mapped->uuid}", ['name' => 'Cabai Keriting', 'unit' => 'kg', 'api_reference_code' => 'com_14'])
            ->assertRedirect('/master/komoditas');

        $this->assertDatabaseHas('commodities', ['name' => 'Bawang Merah Brebes', 'api_reference_code' => 'com_11']);
    }
}
