<?php

declare(strict_types=1);

namespace Tests\Feature;

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
            'status_aktif' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_komoditas(): void
    {
        $response = $this->get('/master/komoditas');
        $response->assertRedirect('/login');

        $response = $this->post('/master/komoditas', [
            'nama_komoditas' => 'Beras Medium',
            'satuan' => 'kg',
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
            ->has('kategori_options')
        );
    }

    public function test_can_filter_and_search_komoditas(): void
    {
        Komoditas::factory()->create([
            'nama_komoditas' => 'Gula Pasir Kristal',
            'sku' => 'GLA-001',
            'kategori' => 'Gula & Pemanis',
            'satuan' => 'kg',
        ]);

        Komoditas::factory()->create([
            'nama_komoditas' => 'Minyak Sawit Curah',
            'sku' => 'MYK-001',
            'kategori' => 'Minyak & Lemak',
            'satuan' => 'liter',
        ]);

        $response = $this->actingAs($this->user)->get('/master/komoditas?search=gula');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Index')
            ->has('komoditas.data', 1)
            ->where('komoditas.data.0.sku', 'GLA-001')
            ->where('komoditas.data.0.spesifikasi', null)
        );

        $response = $this->actingAs($this->user)->get('/master/komoditas?kategori=Minyak+%26+Lemak');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Index')
            ->has('komoditas.data', 1)
            ->where('komoditas.data.0.sku', 'MYK-001')
        );

        $response = $this->actingAs($this->user)->get('/master/komoditas?kategori=all');
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
            ->has('kategori_options')
        );
    }

    public function test_can_create_new_komoditas_with_valid_data(): void
    {
        $payload = [
            'nama_komoditas' => 'Beras Rojo Lele 50kg',
            'satuan' => 'sak',
            'sku' => 'BRS-RJL-50',
            'kategori' => 'Beras & Padi',
            'kode_referensi_api' => '102',
            'spesifikasi' => 'Kadar air maksimal 14 persen',
            'status' => true,
        ];

        $response = $this->actingAs($this->user)->post('/master/komoditas', $payload);

        $response->assertRedirect('/master/komoditas');
        $this->assertDatabaseHas('komoditas', [
            'nama_komoditas' => 'Beras Rojo Lele 50kg',
            'slug' => 'beras-rojo-lele-50kg',
            'sku' => 'BRS-RJL-50',
            'satuan' => 'sak',
            'kategori' => 'Beras & Padi',
            'status' => true,
        ]);

        $created = Komoditas::where('sku', 'BRS-RJL-50')->firstOrFail();
        $this->assertTrue(Str::isUuid($created->uuid));
    }

    public function test_create_komoditas_generates_unique_slug_when_name_collides(): void
    {
        Komoditas::factory()->create([
            'nama_komoditas' => 'Beras Ramos Super',
            'slug' => 'beras-ramos-super',
            'sku' => 'SKU-001',
        ]);

        $payload = [
            'nama_komoditas' => 'Beras Ramos Super Baru',
            'satuan' => 'kg',
            'sku' => 'SKU-002',
        ];

        $response = $this->actingAs($this->user)->post('/master/komoditas', $payload);

        $response->assertRedirect('/master/komoditas');
        $this->assertDatabaseHas('komoditas', [
            'sku' => 'SKU-002',
            'slug' => 'beras-ramos-super-baru',
        ]);
    }

    public function test_create_komoditas_validation_fails_on_empty_name_or_invalid_satuan(): void
    {
        $response = $this->actingAs($this->user)->post('/master/komoditas', [
            'nama_komoditas' => '',
            'satuan' => 'invalid-satuan',
        ]);

        $response->assertSessionHasErrors(['nama_komoditas', 'satuan']);
        $this->assertDatabaseCount('komoditas', 0);
    }

    public function test_can_render_edit_page(): void
    {
        $komoditas = Komoditas::factory()->create([
            'nama_komoditas' => 'Jagung Pipil Kering',
        ]);

        $response = $this->actingAs($this->user)->get("/master/komoditas/{$komoditas->uuid}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Master/Komoditas/Edit')
            ->where('komoditas.data.uuid', $komoditas->uuid)
            ->where('komoditas.data.nama_komoditas', 'Jagung Pipil Kering')
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
            'nama_komoditas' => 'Kedelai Impor Lama',
            'slug' => 'kedelai-impor-lama',
            'sku' => 'KDL-001',
            'satuan' => 'kg',
        ]);

        $response = $this->actingAs($this->user)->put("/master/komoditas/{$komoditas->uuid}", [
            'nama_komoditas' => 'Kedelai Impor Premium',
            'sku' => 'KDL-001',
            'satuan' => 'ton',
            'kategori' => 'Biji-bijian & Jagung',
            'status' => false,
        ]);

        $response->assertRedirect('/master/komoditas');
        $this->assertDatabaseHas('komoditas', [
            'uuid' => $komoditas->uuid,
            'nama_komoditas' => 'Kedelai Impor Premium',
            'slug' => 'kedelai-impor-premium',
            'satuan' => 'ton',
            'status' => false,
        ]);
    }

    public function test_can_soft_delete_komoditas(): void
    {
        $komoditas = Komoditas::factory()->create([
            'nama_komoditas' => 'Tepung Terigu Cakra',
            'sku' => 'TPG-CKR',
        ]);

        $response = $this->actingAs($this->user)->delete("/master/komoditas/{$komoditas->uuid}");

        $response->assertRedirect('/master/komoditas');
        $this->assertSoftDeleted('komoditas', [
            'id' => $komoditas->id,
            'uuid' => $komoditas->uuid,
        ]);
    }
}
