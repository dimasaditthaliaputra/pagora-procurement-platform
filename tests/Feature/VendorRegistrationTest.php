<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Mail\OtpMail;
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
        $this->postJson('/register/vendor/otp', ['email_kontak' => $email])->assertOk();

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

        $this->postJson('/register/vendor/otp', ['email_kontak' => 'baru@vendor.co.id'])
            ->assertOk();

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('baru@vendor.co.id'));
    }

    public function test_otp_cannot_be_requested_for_an_invalid_or_registered_email(): void
    {
        Mail::fake();

        Vendor::create([
            'nama_perusahaan' => 'PT Sudah Ada',
            'slug' => 'pt-sudah-ada',
            'email_kontak' => 'duplikat@perusahaan.com',
            'status_verifikasi' => 'pending',
            'status_aktif' => true,
        ]);

        $this->postJson('/register/vendor/otp', ['email_kontak' => 'bukan-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email_kontak');

        $this->postJson('/register/vendor/otp', ['email_kontak' => 'duplikat@perusahaan.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email_kontak');

        Mail::assertNothingSent();
    }

    public function test_otp_cannot_be_resent_during_cooldown(): void
    {
        Mail::fake();

        $this->postJson('/register/vendor/otp', ['email_kontak' => 'baru@vendor.co.id'])->assertOk();
        $this->postJson('/register/vendor/otp', ['email_kontak' => 'baru@vendor.co.id'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email_kontak');

        Mail::assertSentCount(1);
    }

    public function test_registration_is_rejected_with_wrong_otp(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->requestOtp('procurement@agropangan.co.id');

        $response = $this->from('/register/vendor')->post('/register/vendor', [
            'nama_perusahaan' => 'PT Agro Pangan Nusantara',
            'email_kontak' => 'procurement@agropangan.co.id',
            'no_telepon' => '081234567890',
            'alamat' => 'Bekasi',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'otp' => '000000',
            'dokumen_legalitas' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
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
            'nama_perusahaan' => 'PT Agro Pangan Nusantara',
            'email_kontak' => 'lain@agropangan.co.id',
            'no_telepon' => '081234567890',
            'alamat' => 'Bekasi',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'otp' => $code,
            'dokumen_legalitas' => UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'),
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
            'nama_perusahaan' => 'PT Agro Pangan Nusantara',
            'email_kontak' => 'procurement@agropangan.co.id',
            'no_telepon' => '081234567890',
            'alamat' => 'Kawasan Industri Cikarang Blok C-12, Bekasi',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'otp' => $otp,
            'dokumen_legalitas' => $file,
        ];

        $response = $this->from('/register/vendor')
            ->post('/register/vendor', $payload);

        $response->assertRedirect('/register/vendor');
        $response->assertSessionHas(
            'status',
            'Pendaftaran berhasil disubmit dan sedang dalam tahap validasi Manajemen.'
        );

        // Memastikan data vendor tersimpan dengan status pending.
        $this->assertDatabaseHas('vendor', [
            'nama_perusahaan' => 'PT Agro Pangan Nusantara',
            'email_kontak' => 'procurement@agropangan.co.id',
            'no_telepon' => '081234567890',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'status_verifikasi' => 'pending',
            'status_aktif' => true,
        ]);

        $vendor = Vendor::where(
            'email_kontak',
            'procurement@agropangan.co.id'
        )->firstOrFail();

        $this->assertNotNull($vendor->uuid);
        $this->assertNotNull($vendor->slug);
        $this->assertNotNull($vendor->dokumen_legalitas);

        // Memastikan dokumen tersimpan di penyimpanan privat.
        Storage::disk('local')->assertExists($vendor->dokumen_legalitas);

        // Memastikan data dokumen legalitas masuk ke tabel khusus.
        $this->assertDatabaseHas('dokumen_legalitas_vendor', [
            'vendor_id' => $vendor->getKey(),
            'jenis_dokumen' => 'dokumen_legalitas',
            'file_url' => $vendor->dokumen_legalitas,
            'status_verifikasi' => 'pending',
            'verif_by' => null,
            'alasan_penolakan' => null,
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
            'nama_perusahaan',
            'email_kontak',
            'no_telepon',
            'alamat',
            'kategori_komoditas',
            'otp',
            'dokumen_legalitas',
        ]);

        $this->assertSame(0, Vendor::count());
    }

    public function test_registration_fails_when_email_already_registered_as_vendor(): void
    {
        Storage::fake('local');

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

        $file = UploadedFile::fake()->create(
            'dokumen.pdf',
            500,
            'application/pdf'
        );

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
        Storage::fake('local');

        $largeFile = UploadedFile::fake()->create(
            'dokumen_besar.pdf',
            2500,
            'application/pdf'
        );

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
