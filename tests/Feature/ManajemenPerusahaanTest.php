<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\PerusahaanBuyer;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManajemenPerusahaanTest extends TestCase
{
    use RefreshDatabase;

    private PerusahaanBuyer $tenant;

    private Vendor $vendor;

    private User $ownerUser;

    private User $vendorUser;

    private User $purchasingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // 1. Setup Tenant & Owner
        $this->tenant = PerusahaanBuyer::factory()->create([
            'company_name' => 'PT Pagora Sumber Makmur',
            'tax_number' => '01.234.567.8-111.000',
            'address' => 'Jl. Sudirman No. 10 Jakarta Pusat',
            'verification_status' => VerificationStatus::Approved,
        ]);

        $this->ownerUser = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Owner->value)->value('id'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // 2. Setup Vendor & Vendor User
        $this->vendor = Vendor::create([
            'company_name' => 'CV Berkah Tani Nusantara',
            'slug' => 'cv-berkah-tani-nusantara-abc123',
            'tax_number' => '02.345.678.9-222.000',
            'contact_email' => 'kontak@berkahtani.test',
            'phone' => '081234567890',
            'address' => 'Jl. Malioboro No. 25 Yogyakarta',
            'kategori_komoditas' => 'Beras Organik',
            'verification_status' => VerificationStatus::Approved->value,
            'is_active' => true,
        ]);

        $this->vendorUser = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Vendor->value)->value('id'),
            'vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        // 3. Setup Staff User (Purchasing)
        $this->purchasingUser = User::factory()->create([
            'role_id' => Role::where('name', RoleName::Purchasing->value)->value('id'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_manajemen_perusahaan(): void
    {
        $this->get('/manajemen/perusahaan')->assertRedirect('/login');
        $this->put('/manajemen/perusahaan', [
            'company_name' => 'PT Baru',
        ])->assertRedirect('/login');
    }

    public function test_staff_role_cannot_access_manajemen_perusahaan(): void
    {
        $this->actingAs($this->purchasingUser)
            ->get('/manajemen/perusahaan')
            ->assertForbidden();

        $this->actingAs($this->purchasingUser)
            ->put('/manajemen/perusahaan', [
                'company_name' => 'PT Modifikasi Ilegal',
                'tax_number' => '01.234.567.8-111.000',
                'address' => 'Jl. Baru No. 1',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_view_own_company_profile(): void
    {
        $response = $this->actingAs($this->ownerUser)->get('/manajemen/perusahaan');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manajemen/Perusahaan/Index')
                ->where('perusahaan.tipe', 'tenant')
                ->where('perusahaan.uuid', $this->tenant->uuid)
                ->where('perusahaan.company_name', 'PT Pagora Sumber Makmur')
                ->where('perusahaan.tax_number', '01.234.567.8-111.000')
                ->where('perusahaan.address', 'Jl. Sudirman No. 10 Jakarta Pusat')
                ->where('perusahaan.verification_status', 'approved')
                ->missing('perusahaan.id')
            );
    }

    public function test_owner_can_update_own_company_profile(): void
    {
        $response = $this->actingAs($this->ownerUser)->put('/manajemen/perusahaan', [
            'company_name' => 'PT Pagora Agro Mandiri',
            'tax_number' => '01.999.888.7-666.000',
            'address' => 'Jl. Gatot Subroto Kav. 55 Jakarta Selatan',
        ]);

        $response->assertRedirect('/manajemen/perusahaan');
        $response->assertSessionHas('success', 'Data profil perusahaan berhasil diperbarui.');

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'company_name' => 'PT Pagora Agro Mandiri',
            'tax_number' => '01.999.888.7-666.000',
            'address' => 'Jl. Gatot Subroto Kav. 55 Jakarta Selatan',
        ]);
    }

    public function test_owner_cannot_use_tax_number_already_registered_by_another_tenant(): void
    {
        PerusahaanBuyer::factory()->create([
            'tax_number' => '01.555.444.3-222.000',
        ]);

        $response = $this->actingAs($this->ownerUser)->put('/manajemen/perusahaan', [
            'company_name' => 'PT Ganti Nama',
            'tax_number' => '01.555.444.3-222.000',
            'address' => 'Jl. Baru No. 100',
        ]);

        $response->assertSessionHasErrors(['tax_number']);
    }

    public function test_owner_can_keep_their_current_tax_number_during_update(): void
    {
        $response = $this->actingAs($this->ownerUser)->put('/manajemen/perusahaan', [
            'company_name' => 'PT Nama Berubah Saja',
            'tax_number' => '01.234.567.8-111.000', // same as current
            'address' => 'Jl. Sudirman No. 10 Jakarta Pusat',
        ]);

        $response->assertRedirect('/manajemen/perusahaan');
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'company_name' => 'PT Nama Berubah Saja',
        ]);
    }

    public function test_vendor_can_view_own_company_profile(): void
    {
        $response = $this->actingAs($this->vendorUser)->get('/manajemen/perusahaan');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manajemen/Perusahaan/Index')
                ->where('perusahaan.tipe', 'vendor')
                ->where('perusahaan.uuid', $this->vendor->uuid)
                ->where('perusahaan.company_name', 'CV Berkah Tani Nusantara')
                ->where('perusahaan.contact_email', 'kontak@berkahtani.test')
                ->where('perusahaan.phone', '081234567890')
                ->where('perusahaan.address', 'Jl. Malioboro No. 25 Yogyakarta')
                ->where('perusahaan.verification_status', 'approved')
                ->missing('perusahaan.id')
            );
    }

    public function test_vendor_can_update_own_company_profile_and_slug_is_updated(): void
    {
        $oldSlug = $this->vendor->slug;

        $response = $this->actingAs($this->vendorUser)->put('/manajemen/perusahaan', [
            'company_name' => 'CV Berkah Tani Mandiri Sejahtera',
            'tax_number' => '02.999.888.7-111.000',
            'contact_email' => 'admin@berkahtani.com',
            'phone' => '089876543210',
            'address' => 'Jl. Gejayan No. 10 Yogyakarta',
        ]);

        $response->assertRedirect('/manajemen/perusahaan');
        $response->assertSessionHas('success', 'Data profil perusahaan berhasil diperbarui.');

        $this->vendor->refresh();

        $this->assertSame('CV Berkah Tani Mandiri Sejahtera', $this->vendor->company_name);
        $this->assertSame('02.999.888.7-111.000', $this->vendor->tax_number);
        $this->assertSame('admin@berkahtani.com', $this->vendor->contact_email);
        $this->assertSame('089876543210', $this->vendor->phone);
        $this->assertSame('Jl. Gejayan No. 10 Yogyakarta', $this->vendor->address);
        $this->assertNotSame($oldSlug, $this->vendor->slug);
        $this->assertStringStartsWith('cv-berkah-tani-mandiri-sejahtera-', $this->vendor->slug);
    }

    public function test_vendor_cannot_use_email_or_tax_number_already_registered_by_another_vendor(): void
    {
        Vendor::create([
            'company_name' => 'CV Rival',
            'slug' => 'cv-rival-'.Str::lower(Str::random(6)),
            'tax_number' => '03.111.222.3-444.000',
            'contact_email' => 'rival@vendor.test',
            'phone' => '0811111111',
            'address' => 'Alamat Rival',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        // Duplicate tax number
        $this->actingAs($this->vendorUser)->put('/manajemen/perusahaan', [
            'company_name' => 'CV Berkah Tani Nusantara',
            'tax_number' => '03.111.222.3-444.000',
            'contact_email' => 'kontak@berkahtani.test',
            'phone' => '081234567890',
            'address' => 'Jl. Malioboro No. 25 Yogyakarta',
        ])->assertSessionHasErrors(['tax_number']);

        // Duplicate contact email
        $this->actingAs($this->vendorUser)->put('/manajemen/perusahaan', [
            'company_name' => 'CV Berkah Tani Nusantara',
            'tax_number' => '02.345.678.9-222.000',
            'contact_email' => 'rival@vendor.test',
            'phone' => '081234567890',
            'address' => 'Jl. Malioboro No. 25 Yogyakarta',
        ])->assertSessionHasErrors(['contact_email']);
    }

    public function test_invalid_npwp_format_is_rejected(): void
    {
        $response = $this->actingAs($this->ownerUser)->put('/manajemen/perusahaan', [
            'company_name' => 'PT Baru',
            'tax_number' => '123456', // invalid length & format
            'address' => 'Jl. Alamat Valid Minimal Lima Karakter',
        ]);

        $response->assertSessionHasErrors(['tax_number']);
    }

    public function test_api_json_response(): void
    {
        // 1. Get JSON
        $getResponse = $this->actingAs($this->ownerUser)
            ->getJson('/manajemen/perusahaan');

        $getResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_name', 'PT Pagora Sumber Makmur');

        // 2. Put JSON
        $putResponse = $this->actingAs($this->ownerUser)
            ->putJson('/manajemen/perusahaan', [
                'company_name' => 'PT Pagora Updated Via API',
                'tax_number' => '01.234.567.8-111.000',
                'address' => 'Jl. Alamat Baru No. 123',
            ]);

        $putResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_name', 'PT Pagora Updated Via API');
    }
}
