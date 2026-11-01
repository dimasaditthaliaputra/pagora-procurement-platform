<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CommodityCategory;
use App\Models\Komoditas;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KomoditasTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->user = User::factory()->create([
            'role_id' => 1, // Superadmin
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_komoditas(): void
    {
        $response = $this->get('/master/komoditas');
        $response->assertRedirect('/login');

        $response = $this->post('/master/komoditas', [
            'name' => 'Beras Medium',
            'unit' => 'kg',
        ]);
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_komoditas_index(): void
    {
        Komoditas::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get('/master/komoditas');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Index')
            ->has('komoditas.data', 3)
            ->has('satuan_options')
            ->has('category_options')
        );
    }

    public function test_can_filter_and_search_komoditas(): void
    {
        $gula = CommodityCategory::factory()->create(['name' => 'Gula & Pemanis']);
        $minyak = CommodityCategory::factory()->create(['name' => 'Minyak & Lemak']);

        Komoditas::factory()->create([
            'name' => 'Gula Pasir Kristal',
            'sku' => 'GLA-001',
            'commodity_category_id' => $gula->id,
            'unit' => 'kg',
        ]);

        Komoditas::factory()->create([
            'name' => 'Minyak Sawit Curah',
            'sku' => 'MYK-001',
            'commodity_category_id' => $minyak->id,
            'unit' => 'liter',
        ]);

        $response = $this->actingAs($this->user)->get('/master/komoditas?search=gula');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Index')
            ->has('komoditas.data', 1)
            ->where('komoditas.data.0.sku', 'GLA-001')
            ->where('komoditas.data.0.category_name', 'Gula & Pemanis')
            ->where('komoditas.data.0.commodity_category_uuid', $gula->uuid)
        );

        $response = $this->actingAs($this->user)->get('/master/komoditas?commodity_category_uuid='.$minyak->uuid);
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Index')
            ->has('komoditas.data', 1)
            ->where('komoditas.data.0.sku', 'MYK-001')
        );

        $response = $this->actingAs($this->user)->get('/master/komoditas?commodity_category_uuid=all');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Index')
            ->has('komoditas.data', 2)
        );

        $response = $this->actingAs($this->user)->get('/master/komoditas?status=false');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Index')
            ->has('komoditas.data', 0)
        );
    }

    public function test_can_render_create_page(): void
    {
        $response = $this->actingAs($this->user)->get('/master/komoditas/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Create')
            ->has('satuan_options')
            ->has('category_options')
        );
    }

    public function test_can_create_new_komoditas_with_valid_data(): void
    {
        $category = CommodityCategory::factory()->create(['name' => 'Beras & Padi']);

        $payload = [
            'name' => 'Beras Rojo Lele 50kg',
            'unit' => 'sak',
            'sku' => 'BRS-RJL-50',
            'commodity_category_uuid' => $category->uuid,
            'api_reference_code' => '102',
            'specification' => 'Kadar air maksimal 14 persen',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)->post('/master/komoditas', $payload);

        $response->assertRedirect('/master/komoditas');
        $this->assertDatabaseHas('commodities', [
            'name' => 'Beras Rojo Lele 50kg',
            'slug' => 'beras-rojo-lele-50kg',
            'sku' => 'BRS-RJL-50',
            'unit' => 'sak',
            'commodity_category_id' => $category->id,
            'is_active' => true,
        ]);

        $created = Komoditas::where('sku', 'BRS-RJL-50')->firstOrFail();
        $this->assertTrue(Str::isUuid($created->uuid));
    }

    public function test_create_komoditas_generates_unique_slug_when_name_collides(): void
    {
        Komoditas::factory()->create([
            'name' => 'Beras Ramos Super',
            'slug' => 'beras-ramos-super',
            'sku' => 'SKU-001',
        ]);

        $payload = [
            'name' => 'Beras Ramos Super Baru',
            'unit' => 'kg',
            'sku' => 'SKU-002',
        ];

        $response = $this->actingAs($this->user)->post('/master/komoditas', $payload);

        $response->assertRedirect('/master/komoditas');
        $this->assertDatabaseHas('commodities', [
            'sku' => 'SKU-002',
            'slug' => 'beras-ramos-super-baru',
        ]);
    }

    public function test_create_komoditas_validation_fails_on_empty_name_or_invalid_unit(): void
    {
        $response = $this->actingAs($this->user)->post('/master/komoditas', [
            'name' => '',
            'unit' => 'invalid-satuan',
        ]);

        $response->assertSessionHasErrors(['name', 'unit']);
        $this->assertDatabaseCount('commodities', 0);
    }

    public function test_can_render_edit_page(): void
    {
        $komoditas = Komoditas::factory()->create([
            'name' => 'Jagung Pipil Kering',
        ]);

        $response = $this->actingAs($this->user)->get("/master/komoditas/{$komoditas->uuid}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Edit')
            ->where('komoditas.data.uuid', $komoditas->uuid)
            ->where('komoditas.data.name', 'Jagung Pipil Kering')
        );
    }

    public function test_edit_returns_404_when_uuid_does_not_exist(): void
    {
        $randomUuid = (string) Str::uuid();

        $response = $this->actingAs($this->user)->get("/master/komoditas/{$randomUuid}/edit");

        $response->assertSessionHas('error', 'Komoditas tidak ditemukan.');
    }

    public function test_can_update_komoditas_and_update_slug_on_name_change(): void
    {
        $komoditas = Komoditas::factory()->create([
            'name' => 'Kedelai Impor Lama',
            'slug' => 'kedelai-impor-lama',
            'sku' => 'KDL-001',
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->user)->put("/master/komoditas/{$komoditas->uuid}", [
            'name' => 'Kedelai Impor Premium',
            'sku' => 'KDL-001',
            'unit' => 'ton',
            'is_active' => false,
        ]);

        $response->assertRedirect('/master/komoditas');
        $this->assertDatabaseHas('commodities', [
            'uuid' => $komoditas->uuid,
            'name' => 'Kedelai Impor Premium',
            'slug' => 'kedelai-impor-premium',
            'unit' => 'ton',
            'is_active' => false,
        ]);
    }

    public function test_can_soft_delete_komoditas(): void
    {
        $komoditas = Komoditas::factory()->create([
            'name' => 'Tepung Terigu Cakra',
            'sku' => 'TPG-CKR',
        ]);

        $response = $this->actingAs($this->user)->delete("/master/komoditas/{$komoditas->uuid}");

        $response->assertRedirect('/master/komoditas');
        $this->assertSoftDeleted('commodities', [
            'id' => $komoditas->id,
            'uuid' => $komoditas->uuid,
        ]);
    }

    public function test_create_komoditas_accepts_valid_decimal_with_dot_for_default_shrinkage_tolerance(): void
    {
        $payload = [
            'name' => 'Gula Pasir Kristal 50kg',
            'unit' => 'karung',
            'sku' => 'GLA-50',
            'default_shrinkage_tolerance' => '2.75',
        ];

        $response = $this->actingAs($this->user)->post('/master/komoditas', $payload);

        $response->assertRedirect('/master/komoditas');
        $this->assertDatabaseHas('commodities', [
            'sku' => 'GLA-50',
            'default_shrinkage_tolerance' => 2.75,
        ]);
    }

    public function test_create_komoditas_rejects_comma_format_for_default_shrinkage_tolerance(): void
    {
        $payload = [
            'name' => 'Minyak Goreng Sawit 18L',
            'unit' => 'liter',
            'default_shrinkage_tolerance' => '2,5',
        ];

        $response = $this->actingAs($this->user)->post('/master/komoditas', $payload);

        $response->assertSessionHasErrors(['default_shrinkage_tolerance']);
    }

    public function test_create_komoditas_rejects_default_shrinkage_tolerance_greater_than_100(): void
    {
        $payload = [
            'name' => 'Garam Halus',
            'unit' => 'kg',
            'default_shrinkage_tolerance' => '100.5',
        ];

        $response = $this->actingAs($this->user)->post('/master/komoditas', $payload);

        $response->assertSessionHasErrors(['default_shrinkage_tolerance']);
    }

    public function test_create_komoditas_rejects_unknown_or_inactive_category(): void
    {
        $inactive = CommodityCategory::factory()->create(['is_active' => false]);

        foreach ([(string) Str::uuid(), $inactive->uuid] as $uuid) {
            $response = $this->actingAs($this->user)->post('/master/komoditas', [
                'name' => 'Komoditas Uji',
                'unit' => 'kg',
                'commodity_category_uuid' => $uuid,
            ]);

            $response->assertSessionHasErrors(['commodity_category_uuid']);
        }

        $this->assertDatabaseMissing('commodities', ['name' => 'Komoditas Uji']);
    }

    public function test_category_options_only_list_active_categories(): void
    {
        CommodityCategory::factory()->create(['name' => 'Aktif', 'is_active' => true]);
        CommodityCategory::factory()->create(['name' => 'Nonaktif', 'is_active' => false]);

        $response = $this->actingAs($this->user)->get('/master/komoditas/create');

        $response->assertInertia(fn ($page) => $page
            ->has('category_options', 1)
            ->where('category_options.0.name', 'Aktif')
        );
    }
}
