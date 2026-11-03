<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\VendorApprovalMail;
use App\Models\DokumenLegalitasVendor;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
            'name' => 'Super Administrator',
            'email' => 'superadmin@pagora.test',
            'is_active' => true,
        ]);

        $this->buyer = User::factory()->create([
            'role_id' => 2, // Buyer
            'name' => 'Staff Buyer',
            'email' => 'buyer@pagora.test',
            'is_active' => true,
        ]);
    }

    private function createVendor(array $attributes = []): Vendor
    {
        $name = $attributes['company_name'] ?? 'PT Vendor Uji';
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return Vendor::create(array_merge([
            'company_name' => $name,
            'slug' => $slug,
            'contact_email' => 'vendor@test.com',
            'phone' => '08123456789',
            'verification_status' => 'pending',
            'is_active' => false,
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
            ->get('/master/vendor/verifikasi-dokumen?verification_status=pending');

        $response->assertOk();
    }

    public function test_superadmin_can_approve_vendor_legal_document(): void
    {
        Mail::fake();
        $vendor = $this->createVendor([
            'company_name' => 'PT Mitra Sejahtera',
            'contact_email' => 'mitra@sejahtera.com',
            'phone' => '08111222333',
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'NIB / SIUP',
            'document_number' => 'NIB-99281-2026',
            'file_path' => 'vendor_legal_documents/test.pdf',
            'verification_status' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$document->uuid}/verifikasi", [
                'aksi' => 'approve',
            ]);

        $response->assertRedirect('/master/vendor/verifikasi-dokumen');

        $this->assertDatabaseHas('vendor_legal_documents', [
            'id' => $document->id,
            'verification_status' => 'approved',
            'verified_by' => $this->superadmin->id,
            'rejection_reason' => null,
        ]);

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'verification_status' => 'approved',
        ]);

        Mail::assertQueued(
            VendorApprovalMail::class,
            function (VendorApprovalMail $mail) use ($vendor): bool {
                return $mail->hasTo($vendor->contact_email)
                    && $mail->vendorName === $vendor->company_name
                    && $mail->companyName === $vendor->company_name;
            }
        );
    }

    public function test_superadmin_can_reject_vendor_legal_document_with_reason(): void
    {
        Mail::fake();
        $vendor = $this->createVendor([
            'company_name' => 'CV Logistik Prima',
            'contact_email' => 'info@logistikprima.com',
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'NPWP Badan Usaha',
            'document_number' => '01.234.567.8-901.000',
            'file_path' => 'vendor_legal_documents/npwp.pdf',
            'verification_status' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$document->uuid}/verifikasi", [
                'aksi' => 'reject',
                'rejection_reason' => 'Dokumen lampiran buram dan tidak dapat dibaca.',
            ]);

        $response->assertRedirect('/master/vendor/verifikasi-dokumen');

        $this->assertDatabaseHas('vendor_legal_documents', [
            'id' => $document->id,
            'verification_status' => 'rejected',
            'verified_by' => $this->superadmin->id,
            'rejection_reason' => 'Dokumen lampiran buram dan tidak dapat dibaca.',
        ]);

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'verification_status' => 'rejected',
        ]);
        Mail::assertNotQueued(VendorApprovalMail::class);
    }

    public function test_approval_email_is_sent_only_after_all_documents_are_approved(): void
    {
        Mail::fake();
        $vendor = $this->createVendor(['company_name' => 'PT Dua Dokumen', 'contact_email' => 'dua@dokumen.test']);

        $npwp = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'npwp',
            'file_path' => 'vendor_legal_documents/npwp.pdf',
            'verification_status' => 'pending',
        ]);
        $siup = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'siup',
            'file_path' => 'vendor_legal_documents/siup.pdf',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$npwp->uuid}/verifikasi", ['aksi' => 'approve']);

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'verification_status' => 'pending']);
        Mail::assertNotQueued(VendorApprovalMail::class);

        $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$siup->uuid}/verifikasi", ['aksi' => 'approve']);

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'verification_status' => 'approved']);
        Mail::assertQueued(VendorApprovalMail::class, 1);
    }

    public function test_vendor_is_rejected_without_email_when_any_document_is_rejected(): void
    {
        Mail::fake();
        $vendor = $this->createVendor(['company_name' => 'PT Satu Ditolak', 'contact_email' => 'ditolak@dokumen.test']);

        $npwp = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'npwp',
            'file_path' => 'vendor_legal_documents/npwp.pdf',
            'verification_status' => 'pending',
        ]);
        $siup = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'siup',
            'file_path' => 'vendor_legal_documents/siup.pdf',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$npwp->uuid}/verifikasi", ['aksi' => 'approve']);
        $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$siup->uuid}/verifikasi", [
                'aksi' => 'reject',
                'rejection_reason' => 'Berkas tidak terbaca.',
            ]);

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'verification_status' => 'rejected']);
        Mail::assertNotQueued(VendorApprovalMail::class);
    }

    public function test_index_lists_one_record_per_vendor_with_all_of_its_documents(): void
    {
        $vendor = $this->createVendor(['company_name' => 'PT Satu Record']);
        foreach (['npwp', 'siup'] as $type) {
            DokumenLegalitasVendor::create([
                'vendor_id' => $vendor->id,
                'document_type' => $type,
                'file_path' => "vendor_legal_documents/{$type}.pdf",
                'verification_status' => 'pending',
            ]);
        }

        $this->actingAs($this->superadmin)
            ->get('/master/vendor/verifikasi-dokumen')
            ->assertInertia(fn ($page) => $page
                ->component('Master/Vendor/VerifikasiDokumen/Index')
                ->has('vendors.data', 1)
                ->has('vendors.data.0.documents', 2)
                ->where('vendors.data.0.company_name', 'PT Satu Record')
            );
    }

    public function test_reject_requires_rejection_reason(): void
    {
        $vendor = $this->createVendor([
            'company_name' => 'UD Berkah',
            'contact_email' => 'berkah@gmail.com',
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'NIB',
            'document_number' => 'NIB-12345',
            'file_path' => 'vendor_legal_documents/berkah.pdf',
            'verification_status' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$document->uuid}/verifikasi", [
                'aksi' => 'reject',
                'rejection_reason' => '',
            ]);

        $response->assertSessionHasErrors(['rejection_reason']);
    }

    public function test_cannot_reverify_already_approved_document(): void
    {
        $vendor = $this->createVendor([
            'company_name' => 'PT Sudah Terverifikasi',
            'contact_email' => 'terverifikasi@pt.com',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'NIB',
            'file_path' => 'vendor_legal_documents/ok.pdf',
            'verification_status' => 'approved',
            'verified_by' => $this->superadmin->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/vendor/verifikasi-dokumen/{$document->uuid}/verifikasi", [
                'aksi' => 'approve',
            ]);

        $response->assertSessionHasErrors(['verification_status']);
    }

    public function test_superadmin_can_download_document_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('vendor_legal_documents/test_file.pdf', 'Sample content');

        $vendor = $this->createVendor([
            'company_name' => 'PT File Download',
            'contact_email' => 'download@pt.com',
        ]);

        $document = DokumenLegalitasVendor::create([
            'vendor_id' => $vendor->id,
            'document_type' => 'SIUP',
            'file_path' => 'vendor_legal_documents/test_file.pdf',
            'verification_status' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get("/master/vendor/verifikasi-dokumen/{$document->uuid}/file");

        $response->assertOk();
    }
}
