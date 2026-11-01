<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\DokumenLegalitasVendor;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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

    /**
     * Request an OTP for the email and return the code that was mailed.
     */
    private function requestOtp(string $email): string
    {
        $this->postJson('/register/vendor/otp', ['contact_email' => $email])->assertOk();

        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($email, &$code) {
            $code = $mail->code;

            return $mail->hasTo($email);
        });

        return (string) $code;
    }

    public function test_otp_is_sent_to_the_vendor_email(): void
    {
        Mail::fake();

        $this->postJson('/register/vendor/otp', ['contact_email' => 'baru@vendor.co.id'])
            ->assertOk();

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('baru@vendor.co.id'));
    }

    public function test_otp_cannot_be_requested_for_an_invalid_or_registered_email(): void
    {
        Mail::fake();

        Vendor::create([
            'company_name' => 'PT Sudah Ada',
            'slug' => 'pt-sudah-ada',
            'contact_email' => 'duplikat@perusahaan.com',
            'verification_status' => 'pending',
            'is_active' => true,
        ]);

        $this->postJson('/register/vendor/otp', ['contact_email' => 'bukan-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_email');

        $this->postJson('/register/vendor/otp', ['contact_email' => 'duplikat@perusahaan.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_email');

        Mail::assertNothingSent();
    }

    public function test_otp_cannot_be_resent_during_cooldown(): void
    {
        Mail::fake();

        $this->postJson('/register/vendor/otp', ['contact_email' => 'baru@vendor.co.id'])->assertOk();
        $this->postJson('/register/vendor/otp', ['contact_email' => 'baru@vendor.co.id'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_email');

        Mail::assertSentCount(1);
    }

    public function test_registration_is_rejected_with_wrong_otp(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->requestOtp('procurement@agropangan.co.id');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'company_name' => 'PT Agro Pangan Nusantara',
            'contact_email' => 'procurement@agropangan.co.id',
            'phone' => '081234567890',
            'address' => 'Bekasi',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'otp' => '000000',
            'legal_document' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHasErrors('otp');
        $this->assertSame(0, Vendor::count());
    }

    public function test_registration_is_rejected_when_otp_was_issued_for_another_email(): void
    {
        Storage::fake('local');
        Mail::fake();
        $code = $this->requestOtp('asli@agropangan.co.id');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'company_name' => 'PT Agro Pangan Nusantara',
            'contact_email' => 'lain@agropangan.co.id',
            'phone' => '081234567890',
            'address' => 'Bekasi',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'otp' => $code,
            'legal_document' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('otp');
        $this->assertSame(0, Vendor::count());
    }

    public function test_guest_can_register_as_vendor_candidate_with_file_upload(): void
    {
        Storage::fake('local');
        Mail::fake();
        $otp = $this->requestOtp('procurement@agropangan.co.id');

        $initialUserCount = User::count();
        $initialVendorCount = Vendor::count();

        $file = UploadedFile::fake()->create(
            'dokumen_legalitas_nib.pdf',
            1024,
            'application/pdf'
        );

        $payload = [
            'company_name' => 'PT Agro Pangan Nusantara',
            'contact_email' => 'procurement@agropangan.co.id',
            'phone' => '081234567890',
            'address' => 'Kawasan Industri Cikarang Blok C-12, Bekasi',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'otp' => $otp,
            'legal_document' => $file,
        ];

        $response = $this->from('/register/vendor')
            ->post('/register/vendor', $payload);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHas(
            'status',
            'Pendaftaran berhasil disubmit dan sedang dalam tahap validasi Manajemen.'
        );

        // Memastikan data vendor tersimpan dengan status pending.
        $this->assertDatabaseHas('vendors', [
            'company_name' => 'PT Agro Pangan Nusantara',
            'contact_email' => 'procurement@agropangan.co.id',
            'phone' => '081234567890',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'verification_status' => 'pending',
            'is_active' => true,
        ]);

        $vendor = Vendor::where(
            'contact_email',
            'procurement@agropangan.co.id'
        )->firstOrFail();

        $this->assertNotNull($vendor->uuid);
        $this->assertNotNull($vendor->slug);

        // Memastikan data dokumen legalitas masuk ke tabel khusus.
        $document = DokumenLegalitasVendor::where('vendor_id', $vendor->getKey())->firstOrFail();

        // Memastikan dokumen tersimpan di penyimpanan privat.
        Storage::disk('local')->assertExists($document->file_path);

        $this->assertDatabaseHas('vendor_legal_documents', [
            'vendor_id' => $vendor->getKey(),
            'document_type' => 'legal_document',
            'file_path' => $document->file_path,
            'verification_status' => 'pending',
            'verified_by' => null,
            'rejection_reason' => null,
        ]);

        // Memastikan pendaftaran vendor belum membuat akun pengguna.
        $this->assertSame($initialUserCount, User::count());
        $this->assertSame($initialVendorCount + 1, Vendor::count());
    }

    public function test_registration_validation_fails_when_fields_are_missing(): void
    {
        $response = $this->from('/register/vendor')
            ->post('/register/vendor', []);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHasErrors([
            'company_name',
            'contact_email',
            'phone',
            'address',
            'kategori_komoditas',
            'otp',
            'legal_document',
        ]);

        $this->assertSame(0, Vendor::count());
    }

    public function test_registration_fails_when_email_already_registered_as_vendor(): void
    {
        Storage::fake('local');

        Vendor::create([
            'company_name' => 'PT Sudah Ada',
            'slug' => 'pt-sudah-ada',
            'contact_email' => 'duplikat@perusahaan.com',
            'phone' => '08111111111',
            'address' => 'Surabaya',
            'kategori_komoditas' => 'Logam, Baja & Fabrikasi',
            'verification_status' => 'pending',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create(
            'dokumen.pdf',
            500,
            'application/pdf'
        );

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'company_name' => 'PT Baru Mencoba Email Sama',
            'contact_email' => 'duplikat@perusahaan.com',
            'phone' => '08222222222',
            'address' => 'Jakarta',
            'kategori_komoditas' => 'Logam, Baja & Fabrikasi',
            'legal_document' => $file,
        ]);

        $response->assertSessionHasErrors(['contact_email']);
    }

    public function test_registration_fails_when_file_exceeds_2mb(): void
    {
        Storage::fake('local');

        $largeFile = UploadedFile::fake()->create(
            'dokumen_besar.pdf',
            2500,
            'application/pdf'
        );

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'company_name' => 'PT Ukuran File Besar',
            'contact_email' => 'besar@perusahaan.com',
            'phone' => '081234567890',
            'address' => 'Medan',
            'kategori_komoditas' => 'Beras & Biji-bijian',
            'legal_document' => $largeFile,
        ]);

        $response->assertSessionHasErrors(['legal_document']);
    }
}
