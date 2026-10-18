<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_vendor_registration_page_can_be_rendered(): void
    {
        $response = $this->get('/register/vendor');

        $response->assertStatus(200);
    }

    public function test_legacy_register_vendor_url_redirects_to_new_url(): void
    {
        $response = $this->get('/register-vendor');

        $response->assertRedirect('/register/vendor');
    }

    public function test_guest_can_register_as_vendor_candidate_with_file_upload(): void
    {
        Storage::fake('public');

        $initialUserCount = User::count();
        $initialVendorCount = Vendor::count();

        $file = UploadedFile::fake()->create('dokumen_legalitas_nib.pdf', 1024, 'application/pdf');

        $payload = [
            'nama_perusahaan' => 'PT Agro Pangan Nusantara',
            'email_kontak' => 'procurement@agropangan.co.id',
            'no_telepon' => '081234567890',
            'alamat' => 'Kawasan Industri Cikarang Blok C-12, Bekasi',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'dokumen_legalitas' => $file,
        ];

        $response = $this->from('/register/vendor')->post('/register/vendor', $payload);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHas('status', 'Pendaftaran berhasil disubmit dan sedang dalam tahap validasi Manajemen.');

        // Memastikan record masuk ke tabel vendor dengan status 'pending'
        $this->assertDatabaseHas('vendor', [
            'nama_perusahaan' => 'PT Agro Pangan Nusantara',
            'email_kontak' => 'procurement@agropangan.co.id',
            'no_telepon' => '081234567890',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'status_verifikasi' => 'pending',
            'status_aktif' => true,
        ]);

        $vendor = Vendor::where('email_kontak', 'procurement@agropangan.co.id')->firstOrFail();
        $this->assertNotNull($vendor->uuid);
        $this->assertNotNull($vendor->slug);
        $this->assertNotNull($vendor->dokumen_legalitas);

        // Memastikan file fisik tersimpan di disk public
        Storage::disk('public')->assertExists($vendor->dokumen_legalitas);

        // Memastikan TIDAK membuat user di tabel users
        $this->assertSame($initialUserCount, User::count());
        $this->assertSame($initialVendorCount + 1, Vendor::count());
    }

    public function test_registration_validation_fails_when_fields_are_missing(): void
    {
        $response = $this->from('/register/vendor')->post('/register/vendor', []);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHasErrors([
            'nama_perusahaan',
            'email_kontak',
            'no_telepon',
            'alamat',
            'kategori_komoditas',
            'dokumen_legalitas',
        ]);

        $this->assertSame(0, Vendor::count());
    }

    public function test_registration_fails_when_email_already_registered_as_vendor(): void
    {
        Storage::fake('public');

        Vendor::create([
            'nama_perusahaan' => 'PT Sudah Ada',
            'slug' => 'pt-sudah-ada',
            'email_kontak' => 'duplikat@perusahaan.com',
            'no_telepon' => '08111111111',
            'alamat' => 'Surabaya',
            'kategori_komoditas' => 'Logam, Baja & Fabrikasi',
            'status_verifikasi' => 'pending',
            'status_aktif' => true,
        ]);

        $file = UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'nama_perusahaan' => 'PT Baru Mencoba Email Sama',
            'email_kontak' => 'duplikat@perusahaan.com',
            'no_telepon' => '08222222222',
            'alamat' => 'Jakarta',
            'kategori_komoditas' => 'Logam, Baja & Fabrikasi',
            'dokumen_legalitas' => $file,
        ]);

        $response->assertSessionHasErrors(['email_kontak']);
    }

    public function test_registration_fails_when_file_exceeds_2mb(): void
    {
        Storage::fake('public');

        $largeFile = UploadedFile::fake()->create('dokumen_besar.pdf', 2500, 'application/pdf'); // > 2MB

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'nama_perusahaan' => 'PT Ukuran File Besar',
            'email_kontak' => 'besar@perusahaan.com',
            'no_telepon' => '081234567890',
            'alamat' => 'Medan',
            'kategori_komoditas' => 'Beras & Biji-bijian',
            'dokumen_legalitas' => $largeFile,
        ]);

        $response->assertSessionHasErrors(['dokumen_legalitas']);
    }
}
