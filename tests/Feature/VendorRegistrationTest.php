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
use App\Notifications\GoogleRegistrationPasswordNotification;
use Database\Seeders\CommodityCategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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

    public function test_registration_success_page_can_be_rendered(): void
    {
        $response = $this->withSession(['registered_email' => 'vendor@perusahaan.co.id'])
            ->get('/register/sukses');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/RegisterSuccess')
            ->where('email', 'vendor@perusahaan.co.id')
        );
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

    public function test_vendor_otp_can_be_verified_successfully(): void
    {
        Mail::fake();
        $code = $this->requestOtp('verifikasi@vendor.co.id');

        $response = $this->postJson('/register/vendor/otp/verify', [
            'contact_email' => 'verifikasi@vendor.co.id',
            'otp' => $code,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Kode OTP berhasil diverifikasi.',
        ]);
    }

    public function test_vendor_otp_verification_fails_with_wrong_or_different_code(): void
    {
        Mail::fake();
        $code = $this->requestOtp('verifikasi@vendor.co.id');
        $wrongCode = $code === '123456' ? '654321' : '123456';

        $response = $this->postJson('/register/vendor/otp/verify', [
            'contact_email' => 'verifikasi@vendor.co.id',
            'otp' => $wrongCode,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['otp']);
    }

    public function test_vendor_otp_verification_fails_when_otp_not_requested_or_expired(): void
    {
        $response = $this->postJson('/register/vendor/otp/verify', [
            'contact_email' => 'belumada@vendor.co.id',
            'otp' => '123456',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['otp']);
    }

    /**
     * Build a legal document payload item for registration requests.
     *
     * @return array<string, mixed>
     */
    private function sampleLegalDocument(
        string $filename = 'dok.pdf',
        string $type = 'npwp',
        ?string $number = null,
        int $kb = 100,
    ): array {
        return [
            'document_type' => $type,
            'document_number' => $number,
            'file' => UploadedFile::fake()->create($filename, $kb, 'application/pdf'),
        ];
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
            'legal_documents' => [$this->sampleLegalDocument()],
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
            'legal_documents' => [$this->sampleLegalDocument()],
        ]);

        $response->assertSessionHasErrors('otp');
        $this->assertSame(0, Vendor::count());
    }

    public function test_guest_can_register_as_vendor_candidate_with_multiple_categories_user_and_multiple_pdfs(): void
    {
        Storage::fake('local');
        Mail::fake();
        $otp = $this->requestOtp('procurement@agropangan.co.id');

        $categories = CommodityCategory::query()->orderBy('name')->take(2)->get();
        $this->assertCount(2, $categories);

        $initialUserCount = User::count();
        $initialVendorCount = Vendor::count();

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
            'otp' => $otp,
            'legal_documents' => [
                $this->sampleLegalDocument('dokumen_nib.pdf', 'nib', 'NIB-2026-0091', 4096),
                $this->sampleLegalDocument('dokumen_npwp.pdf', 'npwp', '01.234.567.8-901.000', 4096),
            ],
        ];

        $response = $this->from('/register/vendor')
            ->post('/register/vendor', $payload);

        $response->assertRedirect(route('register.success'));
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

        // Memastikan seluruh dokumen legalitas masuk ke tabel khusus dan penyimpanan privat.
        $documents = DokumenLegalitasVendor::where('vendor_id', $vendor->getKey())->get();
        $this->assertCount(2, $documents);

        /** @var FilesystemAdapter $localDisk */
        $localDisk = Storage::disk('local');

        /** @var FilesystemAdapter $localDisk */
        $localDisk = Storage::disk('local');

        foreach ($documents as $document) {
            $localDisk->assertExists($document->file_path);
            $this->assertSame('pending', $document->verification_status->value);
            $this->assertNull($document->verified_by);
            $this->assertNull($document->rejection_reason);
        }

        $this->assertDatabaseHas('vendor_legal_documents', [
            'vendor_id' => $vendor->getKey(),
            'document_type' => 'nib',
            'document_number' => 'NIB-2026-0091',
        ]);

        $this->assertDatabaseHas('vendor_legal_documents', [
            'vendor_id' => $vendor->getKey(),
            'document_type' => 'npwp',
            'document_number' => '01.234.567.8-901.000',
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
            'legal_documents',
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
            'legal_documents' => [$this->sampleLegalDocument()],
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
            'legal_documents' => [$this->sampleLegalDocument('dokumen.pdf', 'npwp', null, 500)],
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
            'legal_documents' => [
                $this->sampleLegalDocument('dokumen_aman.pdf', 'npwp', null, 4096),
                [
                    'document_type' => 'nib',
                    'file' => $largeFile,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['legal_documents.1.file']);
        $response->assertSessionDoesntHaveErrors(['legal_documents.0.file']);
    }

    public function test_registration_fails_when_uploaded_document_is_not_pdf(): void
    {
        Storage::fake('local');
        $category = CommodityCategory::firstOrFail();

        $imageFile = UploadedFile::fake()->image('scan_npwp.jpg');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Andi Wijaya',
            'email' => 'gambar@perusahaan.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Dokumen Gambar',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'gambar@perusahaan.com',
            'phone' => '081234567890',
            'address' => 'Medan',
            'commodity_category_uuids' => [$category->uuid],
            'legal_documents' => [
                [
                    'document_type' => 'npwp',
                    'file' => $imageFile,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['legal_documents.0.file']);
    }

    public function test_registration_fails_when_legal_documents_do_not_contain_npwp(): void
    {
        Storage::fake('local');
        $category = CommodityCategory::firstOrFail();

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Andi Wijaya',
            'email' => 'nonpwp@perusahaan.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Tanpa NPWP',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'nonpwp@perusahaan.com',
            'phone' => '081234567890',
            'address' => 'Surabaya',
            'commodity_category_uuids' => [$category->uuid],
            'legal_documents' => [
                $this->sampleLegalDocument('dokumen_nib.pdf', 'nib', 'NIB-12345'),
            ],
        ]);

        $response->assertSessionHasErrors(['legal_documents' => 'Dokumen legalitas NPWP Perusahaan wajib diunggah.']);
    }

    public function test_registration_fails_when_legal_document_file_is_empty(): void
    {
        Storage::fake('local');
        $category = CommodityCategory::firstOrFail();
        $emptyFile = UploadedFile::fake()->create('kosong.pdf', 0, 'application/pdf');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Andi Wijaya',
            'email' => 'kosong@perusahaan.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT File Kosong',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => 'kosong@perusahaan.com',
            'phone' => '081234567890',
            'address' => 'Semarang',
            'commodity_category_uuids' => [$category->uuid],
            'legal_documents' => [
                [
                    'document_type' => 'npwp',
                    'file' => $emptyFile,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['legal_documents.0.file']);
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
            'legal_documents' => [$this->sampleLegalDocument()],
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

    public function test_otp_can_be_requested_and_verified_using_email_parameter(): void
    {
        Mail::fake();

        $this->postJson('/register/vendor/otp', ['email' => 'bisnis@mitra.co.id'])
            ->assertOk();

        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('bisnis@mitra.co.id');
        });

        $response = $this->postJson('/register/vendor/otp/verify', [
            'email' => 'bisnis@mitra.co.id',
            'otp' => (string) $code,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Kode OTP berhasil diverifikasi.',
        ]);
    }

    public function test_otp_cannot_be_requested_if_email_already_exists_in_users_table(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'sudahada@mitra.co.id',
        ]);

        $this->postJson('/register/vendor/otp', ['email' => 'sudahada@mitra.co.id'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_email');

        Mail::assertNothingOutgoing();
    }

    public function test_registration_auto_fills_contact_email_from_email_and_marks_email_verified(): void
    {
        Storage::fake('local');
        Mail::fake();
        $otp = $this->requestOtp('bisnis@agronusantara.co.id');
        $category = CommodityCategory::firstOrFail();

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Budi Santoso',
            'email' => 'bisnis@agronusantara.co.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Agro Nusantara Bersama',
            'tax_number' => '02.345.678.9-012.000',
            'phone' => '081398765432',
            'address' => 'Surabaya, Jawa Timur',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $otp,
            'legal_documents' => [
                $this->sampleLegalDocument('dokumen_npwp.pdf', 'npwp', '02.345.678.9-012.000'),
            ],
        ]);

        $response->assertRedirect(route('register.success'));

        $this->assertDatabaseHas('vendors', [
            'company_name' => 'PT Agro Nusantara Bersama',
            'tax_number' => '02.345.678.9-012.000',
            'contact_email' => 'bisnis@agronusantara.co.id',
            'phone' => '+6281398765432',
        ]);

        $user = User::where('email', 'bisnis@agronusantara.co.id')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Budi Santoso', $user->name);
    }

    public function test_vendor_can_register_via_google_without_otp_and_password_and_receives_password_notification(): void
    {
        Storage::fake('local');
        Notification::fake();
        $category = CommodityCategory::firstOrFail();

        $googleSessionData = [
            'name' => 'Vendor Google User',
            'email' => 'vendor_google@gmail.com',
            'google_id' => 'google-vendor-unique-999',
            'avatar' => 'https://lh3.googleusercontent.com/vendor_avatar.jpg',
        ];

        $response = $this->withSession(['google_user' => $googleSessionData])
            ->post('/register/vendor', [
                'company_name' => 'PT Pemasok Mitra Google',
                'tax_number' => '03.456.789.0-123.000',
                'phone' => '081288887777',
                'address' => 'Jl. Industri Agro No. 8, Sidoarjo',
                'commodity_category_uuids' => [$category->uuid],
                'legal_documents' => [
                    $this->sampleLegalDocument('dokumen_npwp.pdf', 'npwp', '03.456.789.0-123.000'),
                ],
                'is_google_registration' => true,
            ]);

        $response->assertRedirect(route('register.success'));

        $this->assertDatabaseHas('vendors', [
            'company_name' => 'PT Pemasok Mitra Google',
            'tax_number' => '03.456.789.0-123.000',
            'contact_email' => 'vendor_google@gmail.com',
        ]);

        $user = User::where('email', 'vendor_google@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-vendor-unique-999', $user->google_id);
        $this->assertSame('https://lh3.googleusercontent.com/vendor_avatar.jpg', $user->avatar);
        $this->assertNotNull($user->email_verified_at);

        Notification::assertSentTo(
            $user,
            GoogleRegistrationPasswordNotification::class,
            function (GoogleRegistrationPasswordNotification $notification) {
                return str_starts_with($notification->defaultPassword, 'Pagora#')
                    && str_contains(strtolower($notification->accountType), 'vendor');
            }
        );

        $this->assertFalse(session()->has('google_user'));
    }

    public function test_vendor_registration_succeeds_with_individual_npwp_nib_and_ktp_files(): void
    {
        Storage::fake('local');
        Mail::fake();
        $code = $this->requestOtp('vendor.tiga.dokumen@perusahaan.com');
        $category = CommodityCategory::firstOrFail();

        $npwpFile = UploadedFile::fake()->create('scan_npwp_asli.pdf', 500, 'application/pdf');
        $nibFile = UploadedFile::fake()->create('scan_nib_asli.pdf', 600, 'application/pdf');
        $ktpFile = UploadedFile::fake()->create('scan_ktp_asli.pdf', 400, 'application/pdf');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Direktur Vendor Tiga',
            'email' => 'vendor.tiga.dokumen@perusahaan.com',
            'contact_email' => 'vendor.tiga.dokumen@perusahaan.com',
            'password' => 'VendorPassword123#',
            'password_confirmation' => 'VendorPassword123#',
            'company_name' => 'PT Tiga Dokumen Sejahtera',
            'tax_number' => '04.567.890.1-234.000',
            'nomor_nib' => 'NIB-9988776655',
            'nomor_ktp' => '3171012345670001',
            'phone' => '081299998888',
            'address' => 'Kawasan Industri MM2100, Cikarang',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $code,
            'dokumen_npwp' => $npwpFile,
            'dokumen_nib' => $nibFile,
            'dokumen_ktp' => $ktpFile,
        ]);

        $response->assertRedirect(route('register.success'));
        $vendor = Vendor::where('company_name', 'PT Tiga Dokumen Sejahtera')->first();
        $this->assertNotNull($vendor);

        $documents = $vendor->legalDocuments;
        $this->assertCount(3, $documents);

        $docTypes = $documents->pluck('document_type')->all();
        $this->assertContains('npwp', $docTypes);
        $this->assertContains('nib', $docTypes);
        $this->assertContains('ktp', $docTypes);

        foreach ($documents as $doc) {
            Storage::disk('local')->assertExists($doc->file_path);
            $filename = basename($doc->file_path);
            $this->assertMatchesRegularExpression('/^vendor_\d+_(npwp|nib|ktp)\.pdf$/', $filename);
        }
    }

    public function test_vendor_registration_requires_sppkp_when_pkp_status_is_ppn(): void
    {
        Storage::fake('local');
        Mail::fake();
        $code = $this->requestOtp('pkp.vendor@test.co.id');
        $category = CommodityCategory::query()->first();

        $npwpFile = UploadedFile::fake()->create('npwp.pdf', 200, 'application/pdf');
        $nibFile = UploadedFile::fake()->create('nib.pdf', 200, 'application/pdf');
        $ktpFile = UploadedFile::fake()->create('ktp.pdf', 200, 'application/pdf');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Budi Santoso',
            'email' => 'pkp.vendor@test.co.id',
            'password' => 'VendorPassword123#',
            'password_confirmation' => 'VendorPassword123#',
            'company_name' => 'PT PKP Tanpa SPPKP',
            'tax_number' => '05.678.901.2-345.000',
            'nomor_nib' => 'NIB-1234567890',
            'nomor_ktp' => '3171012345670002',
            'phone' => '081299997777',
            'address' => 'Jl. Industri No. 10, Jakarta',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $code,
            'pkp_status' => 'ppn',
            'dokumen_npwp' => $npwpFile,
            'dokumen_nib' => $nibFile,
            'dokumen_ktp' => $ktpFile,
        ]);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHasErrors(['dokumen_sppkp']);
        $this->assertNull(Vendor::where('company_name', 'PT PKP Tanpa SPPKP')->first());
    }

    public function test_vendor_registration_succeeds_with_ppn_and_sppkp_and_optional_akta(): void
    {
        Storage::fake('local');
        Mail::fake();
        $code = $this->requestOtp('pkp.lengkap@test.co.id');
        $category = CommodityCategory::query()->first();

        $npwpFile = UploadedFile::fake()->create('npwp.pdf', 200, 'application/pdf');
        $nibFile = UploadedFile::fake()->create('nib.pdf', 200, 'application/pdf');
        $ktpFile = UploadedFile::fake()->create('ktp.pdf', 200, 'application/pdf');
        $sppkpFile = UploadedFile::fake()->create('sppkp.pdf', 300, 'application/pdf');
        $aktaFile = UploadedFile::fake()->create('akta.pdf', 400, 'application/pdf');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Budi Santoso',
            'email' => 'pkp.lengkap@test.co.id',
            'password' => 'VendorPassword123#',
            'password_confirmation' => 'VendorPassword123#',
            'company_name' => 'PT PKP Lengkap Sejahtera',
            'tax_number' => '06.789.012.3-456.000',
            'nomor_nib' => 'NIB-8877665544',
            'nomor_ktp' => '3171012345670003',
            'phone' => '081299996666',
            'address' => 'Jl. Pajak No. 1, Jakarta',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $code,
            'pkp_status' => 'ppn',
            'nomor_sppkp' => 'S-999PKP/WPJ.07/KP.0303/2026',
            'nomor_akta' => 'AHU-0099887.AH.01.01.2026',
            'dokumen_npwp' => $npwpFile,
            'dokumen_nib' => $nibFile,
            'dokumen_ktp' => $ktpFile,
            'dokumen_sppkp' => $sppkpFile,
            'dokumen_akta' => $aktaFile,
        ]);

        $response->assertRedirect(route('register.success'));
        $vendor = Vendor::where('company_name', 'PT PKP Lengkap Sejahtera')->first();
        $this->assertNotNull($vendor);
        $this->assertTrue($vendor->is_pkp);

        $documents = $vendor->legalDocuments;
        $this->assertCount(5, $documents);

        $docTypes = $documents->pluck('document_type')->all();
        $this->assertContains('npwp', $docTypes);
        $this->assertContains('nib', $docTypes);
        $this->assertContains('ktp', $docTypes);
        $this->assertContains('sppkp', $docTypes);
        $this->assertContains('akta', $docTypes);

        $sppkpDoc = $documents->firstWhere('document_type', 'sppkp');
        $this->assertSame('S-999PKP/WPJ.07/KP.0303/2026', $sppkpDoc->document_number);

        $aktaDoc = $documents->firstWhere('document_type', 'akta');
        $this->assertSame('AHU-0099887.AH.01.01.2026', $aktaDoc->document_number);
    }

    public function test_vendor_registration_succeeds_as_non_ppn_without_sppkp(): void
    {
        Storage::fake('local');
        Mail::fake();
        $code = $this->requestOtp('nonppn@test.co.id');
        $category = CommodityCategory::query()->first();

        $npwpFile = UploadedFile::fake()->create('npwp.pdf', 200, 'application/pdf');
        $nibFile = UploadedFile::fake()->create('nib.pdf', 200, 'application/pdf');
        $ktpFile = UploadedFile::fake()->create('ktp.pdf', 200, 'application/pdf');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Budi Non PKP',
            'email' => 'nonppn@test.co.id',
            'password' => 'VendorPassword123#',
            'password_confirmation' => 'VendorPassword123#',
            'company_name' => 'CV Usaha Mikro Mandiri',
            'tax_number' => '07.890.123.4-567.000',
            'nomor_nib' => 'NIB-5544332211',
            'nomor_ktp' => '3171012345670004',
            'phone' => '081299995555',
            'address' => 'Jl. Desa No. 5, Bogor',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $code,
            'pkp_status' => 'non_ppn',
            'dokumen_npwp' => $npwpFile,
            'dokumen_nib' => $nibFile,
            'dokumen_ktp' => $ktpFile,
        ]);

        $response->assertRedirect(route('register.success'));
        $vendor = Vendor::where('company_name', 'CV Usaha Mikro Mandiri')->first();
        $this->assertNotNull($vendor);
        $this->assertFalse($vendor->is_pkp);

        $documents = $vendor->legalDocuments;
        $this->assertCount(3, $documents);
        $this->assertNotContains('sppkp', $documents->pluck('document_type')->all());
    }

    public function test_vendor_registration_requires_akta_and_sk_kemenkumham_for_incorporated_entities(): void
    {
        Storage::fake('local');
        Mail::fake();
        $code = $this->requestOtp('pt.tanpaakta@test.co.id');
        $category = CommodityCategory::query()->first();

        $npwpFile = UploadedFile::fake()->create('npwp.pdf', 200, 'application/pdf');
        $nibFile = UploadedFile::fake()->create('nib.pdf', 200, 'application/pdf');
        $ktpFile = UploadedFile::fake()->create('ktp.pdf', 200, 'application/pdf');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Budi PT',
            'email' => 'pt.tanpaakta@test.co.id',
            'password' => 'VendorPassword123#',
            'password_confirmation' => 'VendorPassword123#',
            'business_entity' => 'pt',
            'company_name' => 'PT Maju Bersama Sukses',
            'tax_number' => '08.901.234.5-678.000',
            'nomor_nib' => 'NIB-9988776655',
            'nomor_ktp' => '3171012345670005',
            'phone' => '081299994444',
            'address' => 'Jl. Korporasi No. 10, Jakarta Pusat',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $code,
            'pkp_status' => 'non_ppn',
            'dokumen_npwp' => $npwpFile,
            'dokumen_nib' => $nibFile,
            'dokumen_ktp' => $ktpFile,
        ]);

        $response->assertSessionHasErrors(['dokumen_akta', 'nomor_akta', 'dokumen_sk_kemenkumham', 'nomor_sk_kemenkumham']);
        $this->assertSame(0, Vendor::where('company_name', 'PT Maju Bersama Sukses')->count());
    }

    public function test_vendor_registration_succeeds_with_pt_entity_and_separate_akta_and_sk_kemenkumham(): void
    {
        Storage::fake('local');
        Mail::fake();
        $code = $this->requestOtp('pt.lengkap@test.co.id');
        $category = CommodityCategory::query()->first();

        $npwpFile = UploadedFile::fake()->create('npwp.pdf', 200, 'application/pdf');
        $nibFile = UploadedFile::fake()->create('nib.pdf', 200, 'application/pdf');
        $ktpFile = UploadedFile::fake()->create('ktp.pdf', 200, 'application/pdf');
        $aktaFile = UploadedFile::fake()->create('akta_pendirian.pdf', 400, 'application/pdf');
        $skFile = UploadedFile::fake()->create('sk_kemenkumham.pdf', 350, 'application/pdf');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Direktur Utama',
            'email' => 'pt.lengkap@test.co.id',
            'password' => 'VendorPassword123#',
            'password_confirmation' => 'VendorPassword123#',
            'business_entity' => 'pt',
            'company_name' => 'PT Prima Mandiri Makmur',
            'tax_number' => '09.012.345.6-789.000',
            'nomor_nib' => 'NIB-1122334455',
            'nomor_ktp' => '3171012345670006',
            'phone' => '081299993333',
            'address' => 'Gedung Wisma Niaga Lt. 5, Jakarta Selatan',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $code,
            'pkp_status' => 'non_ppn',
            'nomor_akta' => 'No. 25 Tanggal 10 Mei 2021',
            'dokumen_akta' => $aktaFile,
            'nomor_sk_kemenkumham' => 'AHU-0012345.AH.01.01.TAHUN 2021',
            'dokumen_sk_kemenkumham' => $skFile,
            'dokumen_npwp' => $npwpFile,
            'dokumen_nib' => $nibFile,
            'dokumen_ktp' => $ktpFile,
        ]);

        $response->assertRedirect(route('register.success'));
        $vendor = Vendor::where('company_name', 'PT Prima Mandiri Makmur')->first();
        $this->assertNotNull($vendor);
        $this->assertSame('pt', $vendor->business_entity);

        $documents = $vendor->legalDocuments;
        $this->assertCount(5, $documents);

        $docTypes = $documents->pluck('document_type')->all();
        $this->assertContains('npwp', $docTypes);
        $this->assertContains('nib', $docTypes);
        $this->assertContains('ktp', $docTypes);
        $this->assertContains('akta', $docTypes);
        $this->assertContains('sk_kemenkumham', $docTypes);

        $aktaDoc = $documents->firstWhere('document_type', 'akta');
        $this->assertSame('No. 25 Tanggal 10 Mei 2021', $aktaDoc->document_number);

        $skDoc = $documents->firstWhere('document_type', 'sk_kemenkumham');
        $this->assertSame('AHU-0012345.AH.01.01.TAHUN 2021', $skDoc->document_number);
    }

    public function test_vendor_registration_succeeds_as_perorangan_without_akta_and_sk_kemenkumham(): void
    {
        Storage::fake('local');
        Mail::fake();
        $code = $this->requestOtp('ud.jaya@test.co.id');
        $category = CommodityCategory::query()->first();

        $npwpFile = UploadedFile::fake()->create('npwp.pdf', 200, 'application/pdf');
        $nibFile = UploadedFile::fake()->create('nib.pdf', 200, 'application/pdf');
        $ktpFile = UploadedFile::fake()->create('ktp.pdf', 200, 'application/pdf');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'name' => 'Joko Pemilik Usaha',
            'email' => 'ud.jaya@test.co.id',
            'password' => 'VendorPassword123#',
            'password_confirmation' => 'VendorPassword123#',
            'business_entity' => 'perorangan',
            'company_name' => 'UD Tani Berkah Sejahtera',
            'tax_number' => '10.123.456.7-890.000',
            'nomor_nib' => 'NIB-3344556677',
            'nomor_ktp' => '3171012345670007',
            'phone' => '081299992222',
            'address' => 'Jl. Pasar Minggu No. 8, Jakarta',
            'commodity_category_uuids' => [$category->uuid],
            'otp' => $code,
            'pkp_status' => 'non_ppn',
            'dokumen_npwp' => $npwpFile,
            'dokumen_nib' => $nibFile,
            'dokumen_ktp' => $ktpFile,
        ]);

        $response->assertRedirect(route('register.success'));
        $vendor = Vendor::where('company_name', 'UD Tani Berkah Sejahtera')->first();
        $this->assertNotNull($vendor);
        $this->assertSame('perorangan', $vendor->business_entity);

        $documents = $vendor->legalDocuments;
        $this->assertCount(3, $documents);
        $this->assertNotContains('akta', $documents->pluck('document_type')->all());
        $this->assertNotContains('sk_kemenkumham', $documents->pluck('document_type')->all());
    }
}
