<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DokumenLegalitasVendor;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerifikasiDokumenTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->superadmin = User::factory()->create([
            'role_id' => 1, // Superadmin
            'nama_lengkap' => 'Super Administrator',
            'email' => 'superadmin@pagora.test',
            'status_aktif' => true,
        ]);

        $this->buyer = User::factory()->create([
            'role_id' => 2, // Buyer
            'nama_lengkap' => 'Staff Buyer',
            'email' => 'buyer@pagora.test',
            'status_aktif' => true,
        ]);
    }

    private function createVendor(array $attributes = []): Vendor
    {
        $name = $attributes['nama_perusahaan'] ?? 'PT Vendor Uji';
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return Vendor::create(array_merge([
            'nama_perusahaan' => $name,
            'slug' => $slug,
            'email_kontak' => 'vendor@test.com',
            'no_telepon' => '08123456789',
            'status_verifikasi' => 'pending',
            'status_aktif' => false,
        ], $attributes));
    }

    public function test_unauthenticated_user_cannot_access_verifikasi_dokumen(): void
    {
        $response = $this->get('/master/vendor/verifikasi-dokumen');
        $response->assertRedirect('/login');
    }

    public function test_non_superadmin_user_cannot_access_verifikasi_dokumen(): void
    {
        $response = $this->actingAs($this->buyer)
            ->get('/master/vendor/verifikasi-dokumen');

        $response->assertForbidden();
    }

    public function test_superadmin_can_access_verifikasi_dokumen_index(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get('/master/vendor/verifikasi-dokumen');

        $response->assertOk();
    }

    public function test_legacy_superadmin_url_redirects_to_new_url(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get('/superadmin/verifikasi-dokumen');

        $response->assertRedirect('/master/vendor/verifikasi-dokumen');
    }

    public function test_superadmin_can_filter_by_status(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get('/master/vendor/verifikasi-dokumen?status_verifikasi=pending');

        $response->assertOk();
    }

    public function test_superadmin_can_approve_vendor_legal_document(): void
    {
        $vendor = $this->createVendor([
            'nama_perusahaan' => 'PT Mitra Sejahtera',
            'email_kontak' => 'mitra@sejahtera.com',
            'no_telepon' => '08111222333',
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'jenis_dokumen' => 'NIB / SIUP',
            'nomor_dokumen' => 'NIB-99281-2026',
            'file_url' => 'vendor_legal_documents/test.pdf',
            'status_verifikasi' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$document->uuid}/verifikasi", [
                'aksi' => 'approve',
            ]);

        $response->assertRedirect('/master/vendor/verifikasi-dokumen');

        $this->assertDatabaseHas('dokumen_legalitas_vendor', [
            'id' => $document->id,
            'status_verifikasi' => 'approved',
            'verif_by' => $this->superadmin->id,
            'alasan_penolakan' => null,
        ]);

        $this->assertDatabaseHas('vendor', [
            'id' => $vendor->id,
            'status_verifikasi' => 'approved',
        ]);
    }

    public function test_superadmin_can_reject_vendor_legal_document_with_reason(): void
    {
        $vendor = $this->createVendor([
            'nama_perusahaan' => 'CV Logistik Prima',
            'email_kontak' => 'info@logistikprima.com',
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'jenis_dokumen' => 'NPWP Badan Usaha',
            'nomor_dokumen' => '01.234.567.8-901.000',
            'file_url' => 'vendor_legal_documents/npwp.pdf',
            'status_verifikasi' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$document->uuid}/verifikasi", [
                'aksi' => 'reject',
                'alasan_penolakan' => 'Dokumen lampiran buram dan tidak dapat dibaca.',
            ]);

        $response->assertRedirect('/master/vendor/verifikasi-dokumen');

        $this->assertDatabaseHas('dokumen_legalitas_vendor', [
            'id' => $document->id,
            'status_verifikasi' => 'rejected',
            'verif_by' => $this->superadmin->id,
            'alasan_penolakan' => 'Dokumen lampiran buram dan tidak dapat dibaca.',
        ]);

        $this->assertDatabaseHas('vendor', [
            'id' => $vendor->id,
            'status_verifikasi' => 'rejected',
        ]);
    }

    public function test_reject_requires_rejection_reason(): void
    {
        $vendor = $this->createVendor([
            'nama_perusahaan' => 'UD Berkah',
            'email_kontak' => 'berkah@gmail.com',
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'jenis_dokumen' => 'NIB',
            'nomor_dokumen' => 'NIB-12345',
            'file_url' => 'vendor_legal_documents/berkah.pdf',
            'status_verifikasi' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$document->uuid}/verifikasi", [
                'aksi' => 'reject',
                'alasan_penolakan' => '',
            ]);

        $response->assertSessionHasErrors(['alasan_penolakan']);
    }

    public function test_cannot_reverify_already_approved_document(): void
    {
        $vendor = $this->createVendor([
            'nama_perusahaan' => 'PT Sudah Terverifikasi',
            'email_kontak' => 'terverifikasi@pt.com',
            'status_verifikasi' => 'approved',
            'status_aktif' => true,
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'jenis_dokumen' => 'NIB',
            'file_url' => 'vendor_legal_documents/ok.pdf',
            'status_verifikasi' => 'approved',
            'verif_by' => $this->superadmin->id,
            'diverifikasi_pada' => now(),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$document->uuid}/verifikasi", [
                'aksi' => 'approve',
            ]);

        $response->assertSessionHasErrors(['status_verifikasi']);
    }

    public function test_superadmin_can_download_document_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('vendor_legal_documents/test_file.pdf', 'Sample content');

        $vendor = $this->createVendor([
            'nama_perusahaan' => 'PT File Download',
            'email_kontak' => 'download@pt.com',
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'jenis_dokumen' => 'SIUP',
            'file_url' => 'vendor_legal_documents/test_file.pdf',
            'status_verifikasi' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get("/master/vendor/verifikasi-dokumen/{$document->uuid}/file");

        $response->assertOk();
    }
}
