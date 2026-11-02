<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Mail\TenantApprovalMail;
use App\Mail\TenantRejectionMail;
use App\Models\PerusahaanBuyer;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VerifikasiTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->superadmin = User::factory()->create([
            'role_id' => 1, // superadmin
            'name' => 'Super Administrator',
            'email' => 'superadmin@pagora.test',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'role_id' => 6, // owner
            'name' => 'Pemilik Tenant',
            'email' => 'owner@pagora.test',
            'is_active' => true,
        ]);
    }

    private function createTenant(array $attributes = []): PerusahaanBuyer
    {
        return PerusahaanBuyer::factory()->create(array_merge([
            'company_name' => 'PT Pengadaan Nusantara',
            'tax_number' => '01.234.567.8-901.000',
            'address' => 'Jl. Jenderal Sudirman No. 10, Jakarta Pusat',
            'verification_status' => VerificationStatus::Pending,
            'is_active' => true,
        ], $attributes));
    }

    public function test_unauthenticated_user_cannot_access_verifikasi_tenant_index(): void
    {
        $response = $this->get('/master/tenant/verifikasi-dokumen');

        $response->assertRedirect('/login');
    }

    public function test_non_superadmin_user_cannot_access_verifikasi_tenant_index(): void
    {
        $response = $this->actingAs($this->owner)
            ->get('/master/tenant/verifikasi-dokumen');

        $response->assertForbidden();
    }

    public function test_superadmin_can_access_verifikasi_tenant_index(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($this->superadmin)
            ->get('/master/tenant/verifikasi-dokumen');

        $response->assertOk();
    }

    public function test_superadmin_can_filter_tenants_by_status_and_search(): void
    {
        $tenantPending = $this->createTenant([
            'company_name' => 'PT Maju Bersama',
            'tax_number' => '11.222.333.4-555.000',
            'verification_status' => VerificationStatus::Pending,
        ]);

        $tenantApproved = $this->createTenant([
            'company_name' => 'PT Sukses Mandiri',
            'tax_number' => '99.888.777.6-555.000',
            'verification_status' => VerificationStatus::Approved,
        ]);

        $responsePending = $this->actingAs($this->superadmin)
            ->get('/master/tenant/verifikasi-dokumen?verification_status=pending');

        $responsePending->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Tenant/VerifikasiDokumen/Index')
                ->has('tenants.data', 1)
                ->where('stats.total', 2)
                ->where('stats.pending', 1)
                ->where('stats.approved', 1)
                ->where('stats.rejected', 0)
            );

        $responseSearch = $this->actingAs($this->superadmin)
            ->get('/master/tenant/verifikasi-dokumen?search=Maju');

        $responseSearch->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/Tenant/VerifikasiDokumen/Index')
                ->has('tenants.data', 1)
                ->where('stats.total', 2)
            );
    }

    public function test_superadmin_can_approve_pending_tenant(): void
    {
        Mail::fake();

        $tenant = $this->createTenant();
        $this->owner->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/tenant/verifikasi-dokumen/{$tenant->uuid}/verifikasi", [
                'aksi' => 'approve',
            ]);

        $response->assertRedirect();

        $tenant->refresh();
        $this->assertEquals(VerificationStatus::Approved, $tenant->verification_status);
        $this->assertEquals($this->superadmin->id, $tenant->verified_by);
        $this->assertNotNull($tenant->verified_at);

        Mail::assertSent(TenantApprovalMail::class, function (TenantApprovalMail $mail) {
            return $mail->hasTo($this->owner->email);
        });
    }

    public function test_superadmin_can_reject_pending_tenant_with_reason(): void
    {
        Mail::fake();

        $tenant = $this->createTenant();
        $this->owner->update(['tenant_id' => $tenant->id]);

        $reason = 'Nomor NPWP tidak sesuai dengan data Ditjen Pajak dan berkas legalitas buram.';

        $response = $this->actingAs($this->superadmin)
            ->post("/master/tenant/verifikasi-dokumen/{$tenant->uuid}/verifikasi", [
                'aksi' => 'reject',
                'rejection_reason' => $reason,
            ]);

        $response->assertRedirect();

        $tenant->refresh();
        $this->assertEquals(VerificationStatus::Rejected, $tenant->verification_status);
        $this->assertEquals($this->superadmin->id, $tenant->verified_by);
        $this->assertEquals($reason, $tenant->verification_note);

        Mail::assertSent(TenantRejectionMail::class, function (TenantRejectionMail $mail) use ($reason) {
            return $mail->hasTo($this->owner->email) && $mail->rejectionReason === $reason;
        });
    }

    public function test_reject_requires_rejection_reason(): void
    {
        Mail::fake();

        $tenant = $this->createTenant();
        $this->owner->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/tenant/verifikasi-dokumen/{$tenant->uuid}/verifikasi", [
                'aksi' => 'reject',
            ]);

        $response->assertSessionHasErrors('rejection_reason');
        Mail::assertNothingSent();
    }

    public function test_cannot_reverify_already_approved_or_rejected_tenant(): void
    {
        Mail::fake();

        $tenant = $this->createTenant([
            'verification_status' => VerificationStatus::Approved,
            'verified_by' => $this->superadmin->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post("/master/tenant/verifikasi-dokumen/{$tenant->uuid}/verifikasi", [
                'aksi' => 'approve',
            ]);

        $response->assertSessionHasErrors(['verification_status']);
        Mail::assertNothingSent();
    }

    public function test_cannot_reverify_already_approved_tenant_json_returns_422(): void
    {
        Mail::fake();

        $tenant = $this->createTenant([
            'verification_status' => VerificationStatus::Approved,
            'verified_by' => $this->superadmin->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/master/tenant/verifikasi-dokumen/{$tenant->uuid}/verifikasi", [
                'aksi' => 'approve',
            ]);

        $response->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_superadmin_can_download_legal_document(): void
    {
        Storage::fake('local');

        $filePath = 'documents/tenants/legal-doc-test.pdf';
        Storage::disk('local')->put($filePath, 'fake content pdf');

        $tenant = $this->createTenant([
            'legal_document_path' => $filePath,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get(route('master.tenant.verifikasi-dokumen.download', $tenant->uuid));

        $response->assertOk();
    }

    public function test_download_missing_legal_document_redirects_with_error(): void
    {
        $tenant = $this->createTenant([
            'legal_document_path' => null,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->get(route('master.tenant.verifikasi-dokumen.download', $tenant->uuid));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    public function test_download_missing_legal_document_json_returns_404(): void
    {
        $tenant = $this->createTenant([
            'legal_document_path' => null,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->getJson(route('master.tenant.verifikasi-dokumen.download', $tenant->uuid));

        $response->assertNotFound();
    }
}
