<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Mail\OtpMail;
use App\Models\CommodityCategory;
use App\Models\DokumenLegalitasVendor;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\CommodityCategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
        $this->seed(CommodityCategorySeeder::class);
    }

    public function test_vendor_registration_page_can_be_rendered(): void
    {
        $response = $this->get('/register/vendor');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/RegisterVendor')
            ->has('commodity_categories')
        );
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
        Mail::assertQueued(OtpMail::class, function (OtpMail $mail) use ($email, &$code) {
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

        Mail::assertQueued(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('baru@vendor.co.id'));
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

        Mail::assertNothingOutgoing();
    }

    public function test_otp_cannot_be_resent_during_cooldown(): void
    {
        Mail::fake();

        $this->postJson('/register/vendor/otp', ['contact_email' => 'baru@vendor.co.id'])->assertOk();
        $this->postJson('/register/vendor/otp', ['contact_email' => 'baru@vendor.co.id'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_email');

        Mail::assertQueuedCount(1);
    }

    public function test_registration_is_rejected_with_wrong_otp(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->requestOtp('procurement@agropangan.co.id');
        $category = CommodityCategory::firstOrFail();

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Andi Wijaya',
            'email' => 'andi@agropangan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Agro Pangan Nusantara',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'procurement@agropangan.co.id',
            'phone' => '081234567890',
            'address' => 'Bekasi',
            'commodity_category_uuids' => [$category->uuid],
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
        $category = CommodityCategory::firstOrFail();

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Andi Wijaya',
            'email' => 'andi@agropangan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Agro Pangan Nusantara',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'lain@agropangan.co.id',
            'phone' => '081234567890',
            'address' => 'Bekasi',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $code,
            'legal_document' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('otp');
        $this->assertSame(0, Vendor::count());
    }

    public function test_guest_can_register_as_vendor_candidate_with_multiple_categories_user_and_file_upload(): void
    {
        Storage::fake('local');
        Mail::fake();
        $otp = $this->requestOtp('procurement@agropangan.co.id');

        $categories = CommodityCategory::query()->orderBy('name')->take(2)->get();
        $this->assertCount(2, $categories);

        $initialUserCount = User::count();
        $initialVendorCount = Vendor::count();

        $file = UploadedFile::fake()->create(
            'dokumen_legalitas_nib.pdf',
            3072, // 3 MB <= 5 MB
            'application/pdf'
        );

        $payload = [
            'name' => 'Andi Wijaya',
            'email' => 'andi@agropangan.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Agro Pangan Nusantara',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'procurement@agropangan.co.id',
            'phone' => '081234567890',
            'address' => 'Kawasan Industri Cikarang Blok C-12, Bekasi',
            'commodity_category_uuids' => $categories->pluck('uuid')->all(),
            'document_type' => 'nib',
            'document_number' => 'NIB-2026-0091',
            'otp' => $otp,
            'legal_document' => $file,
        ];

        $response = $this->from('/register/vendor')
            ->post('/register/vendor', $payload);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHas(
            'status',
            'Pendaftaran sukses! Akun dan dokumen legalitas Anda sedang menunggu verifikasi.'
        );

        // Memastikan data vendor tersimpan dengan NPWP dan status pending.
        $this->assertDatabaseHas('vendors', [
            'company_name' => 'PT Agro Pangan Nusantara',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'procurement@agropangan.co.id',
            'phone' => '+6281234567890',
            'kategori_komoditas' => $categories->pluck('name')->implode(', '),
            'verification_status' => 'pending',
            'is_active' => true,
        ]);

        $vendor = Vendor::where(
            'contact_email',
            'procurement@agropangan.co.id'
        )->firstOrFail();

        $this->assertNotNull($vendor->uuid);
        $this->assertNotNull($vendor->slug);

        // Memastikan pivot vendor_commodity_categories tersimpan.
        foreach ($categories as $category) {
            $this->assertDatabaseHas('vendor_commodity_categories', [
                'vendor_id' => $vendor->getKey(),
                'commodity_category_id' => $category->getKey(),
            ]);
        }

        // Memastikan akun pengguna dengan role vendor dibuat dan terhubung ke vendor.
        $vendorRole = Role::where('name', RoleName::Vendor->value)->firstOrFail();
        $this->assertDatabaseHas('users', [
            'name' => 'Andi Wijaya',
            'email' => 'andi@agropangan.co.id',
            'role_id' => $vendorRole->getKey(),
            'vendor_id' => $vendor->getKey(),
            'is_active' => true,
        ]);

        $user = User::where('email', 'andi@agropangan.co.id')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));

        // Memastikan data dokumen legalitas masuk ke tabel khusus.
        $document = DokumenLegalitasVendor::where('vendor_id', $vendor->getKey())->firstOrFail();

        // Memastikan dokumen tersimpan di penyimpanan privat.
        Storage::disk('local')->assertExists($document->file_path);

        $this->assertDatabaseHas('vendor_legal_documents', [
            'vendor_id' => $vendor->getKey(),
            'document_type' => 'nib',
            'document_number' => 'NIB-2026-0091',
            'file_path' => $document->file_path,
            'verification_status' => 'pending',
            'verified_by' => null,
            'rejection_reason' => null,
        ]);

        $this->assertSame($initialUserCount + 1, User::count());
        $this->assertSame($initialVendorCount + 1, Vendor::count());
    }

    public function test_registration_validation_fails_when_fields_are_missing(): void
    {
        $response = $this->from('/register/vendor')
            ->post('/register/vendor', []);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHasErrors([
            'name',
            'email',
            'password',
            'company_name',
            'tax_number',
            'contact_email',
            'phone',
            'address',
            'commodity_category_uuids',
            'otp',
            'legal_document',
        ]);

        $this->assertSame(0, Vendor::count());
    }

    public function test_registration_fails_with_invalid_npwp_format(): void
    {
        Storage::fake('local');
        Mail::fake();
        $otp = $this->requestOtp('npwp@perusahaan.com');
        $category = CommodityCategory::firstOrFail();

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Andi Wijaya',
            'email' => 'andi.npwp@perusahaan.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT NPWP Salah',
            'tax_number' => '12345-invalid',
            'contact_email' => 'npwp@perusahaan.com',
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $otp,
            'legal_document' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHasErrors(['tax_number']);
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
            'kategori_komoditas' => 'Pangan Pokok',
            'verification_status' => 'pending',
            'is_active' => true,
        ]);

        $category = CommodityCategory::firstOrFail();
        $file = UploadedFile::fake()->create(
            'dokumen.pdf',
            500,
            'application/pdf'
        );

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Andi Wijaya',
            'email' => 'andi@perusahaan.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Baru Mencoba Email Sama',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'duplikat@perusahaan.com',
            'phone' => '08222222222',
            'address' => 'Jakarta',
            'commodity_category_uuids' => [$category->uuid],
            'legal_document' => $file,
        ]);

        $response->assertSessionHasErrors(['contact_email']);
    }

    public function test_registration_fails_when_file_exceeds_5mb(): void
    {
        Storage::fake('local');
        $category = CommodityCategory::firstOrFail();

        $largeFile = UploadedFile::fake()->create(
            'dokumen_besar.pdf',
            6000, // > 5120 KB (5 MB)
            'application/pdf'
        );

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Andi Wijaya',
            'email' => 'besar@perusahaan.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Ukuran File Besar',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'besar@perusahaan.com',
            'phone' => '081234567890',
            'address' => 'Medan',
            'commodity_category_uuids' => [$category->uuid],
            'legal_document' => $largeFile,
        ]);

        $response->assertSessionHasErrors(['legal_document']);
    }

    public function test_phone_number_is_stored_in_e164_format(): void
    {
        Storage::fake('local');
        Mail::fake();
        $code = $this->requestOtp('telepon@perusahaan.com');
        $category = CommodityCategory::firstOrFail();

        $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Andi Wijaya',
            'email' => 'andi.telp@perusahaan.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Telepon Rapi',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'telepon@perusahaan.com',
            'phone' => '0812-3456-7890',
            'address' => 'Bandung',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $code,
            'legal_document' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vendors', [
            'company_name' => 'PT Telepon Rapi',
            'phone' => '+6281234567890',
        ]);
    }

    public function test_invalid_invitation_token_displays_error_message(): void
    {
        $response = $this->get('/register/vendor?invitation=token-tidak-ada');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/RegisterVendor')
            ->where('invitation', null)
            ->where('invitation_error', 'Tautan undangan pendaftaran vendor tidak valid atau sudah kedaluwarsa.')
        );
    }
}
