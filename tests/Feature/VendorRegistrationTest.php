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

        foreach ($documents as $document) {
            Storage::disk('local')->assertExists($document->file_path);
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
}
