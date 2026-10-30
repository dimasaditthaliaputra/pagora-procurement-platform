<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Mail\UndanganVendorMail;
use App\Models\UndanganVendor;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UndangVendorTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $manajemen;

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

        $this->manajemen = User::factory()->create([
            'role_id' => 5, // Manajemen
            'nama_lengkap' => 'Admin Manajemen',
            'email' => 'manajemen@pagora.test',
            'status_aktif' => true,
        ]);

        $this->buyer = User::factory()->create([
            'role_id' => 2, // Buyer
            'nama_lengkap' => 'Staff Buyer',
            'email' => 'buyer@pagora.test',
            'status_aktif' => true,
        ]);
    }

    public function test_superadmin_can_invite_a_new_vendor_and_email_is_sent(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'nama_perusahaan' => 'PT Mitra Sawit Sejahtera',
                'email' => 'mitra@sawit.co.id',
                'kategori_komoditas' => 'Minyak & Lemak Nabati',
                'pesan' => 'Kami mengundang Anda untuk memasok bahan baku CPO.',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/master/vendor');

        $this->assertDatabaseHas('undangan_vendor', [
            'nama_perusahaan' => 'PT Mitra Sawit Sejahtera',
            'email' => 'mitra@sawit.co.id',
            'kategori_komoditas' => 'Minyak & Lemak Nabati',
            'pesan' => 'Kami mengundang Anda untuk memasok bahan baku CPO.',
            'status' => 'pending',
            'invited_by' => $this->superadmin->id,
        ]);

        Mail::assertSent(UndanganVendorMail::class, function (UndanganVendorMail $mail) {
            return $mail->hasTo('mitra@sawit.co.id')
                && $mail->invitation->nama_perusahaan === 'PT Mitra Sawit Sejahtera'
                && str_contains($mail->registrationUrl, $mail->invitation->token);
        });
    }

    public function test_manajemen_can_invite_a_new_vendor(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->manajemen)
            ->post('/master/vendor/undang', [
                'nama_perusahaan' => 'CV Beras Nusantara',
                'email' => 'kontak@berasnusantara.id',
                'kategori_komoditas' => 'Beras & Biji-bijian',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/master/vendor');

        $this->assertDatabaseHas('undangan_vendor', [
            'nama_perusahaan' => 'CV Beras Nusantara',
            'email' => 'kontak@berasnusantara.id',
            'invited_by' => $this->manajemen->id,
        ]);

        Mail::assertSent(UndanganVendorMail::class);
    }

    public function test_buyer_cannot_invite_a_vendor(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->buyer)
            ->post('/master/vendor/undang', [
                'nama_perusahaan' => 'PT Terlarang',
                'email' => 'terlarang@vendor.com',
            ]);

        $response->assertStatus(403);
        Mail::assertNothingSent();
    }

    public function test_guest_cannot_invite_a_vendor(): void
    {
        Mail::fake();

        $response = $this->post('/master/vendor/undang', [
            'nama_perusahaan' => 'PT Anonim',
            'email' => 'anonim@vendor.com',
        ]);

        $response->assertRedirect('/login');
        Mail::assertNothingSent();
    }

    public function test_validation_fails_when_fields_are_invalid(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'nama_perusahaan' => '',
                'email' => 'not-an-email',
            ]);

        $response->assertSessionHasErrors(['nama_perusahaan', 'email']);
        Mail::assertNothingSent();
    }

    public function test_cannot_invite_vendor_with_already_registered_vendor_email(): void
    {
        Mail::fake();

        Vendor::create([
            'nama_perusahaan' => 'PT Vendor Eksis',
            'slug' => 'pt-vendor-eksis-123456',
            'email_kontak' => 'eksis@vendor.co.id',
            'no_telepon' => '081234567890',
            'alamat' => 'Jl. Raya Industri No. 1',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'status_verifikasi' => 'approved',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'nama_perusahaan' => 'PT Vendor Lain',
                'email' => 'eksis@vendor.co.id',
            ]);

        $response->assertSessionHasErrors(['email']);
        Mail::assertNothingSent();
    }

    public function test_cannot_invite_vendor_with_already_registered_user_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'nama_perusahaan' => 'PT Akun Bentrok',
                'email' => $this->manajemen->email,
            ]);

        $response->assertSessionHasErrors(['email']);
        Mail::assertNothingSent();
    }

    public function test_reinviting_vendor_updates_existing_pending_invitation(): void
    {
        Mail::fake();

        // First invitation
        $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'nama_perusahaan' => 'PT Gula Manis',
                'email' => 'gula@manis.test',
                'kategori_komoditas' => 'Gula, Pemanis & Rempah',
            ]);

        $firstToken = UndanganVendor::where('email', 'gula@manis.test')->value('token');

        // Reinvite with updated notes
        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'nama_perusahaan' => 'PT Gula Manis Nusantara',
                'email' => 'gula@manis.test',
                'kategori_komoditas' => 'Gula, Pemanis & Rempah',
                'pesan' => 'Pesan diperbarui.',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, UndanganVendor::where('email', 'gula@manis.test')->count());

        $updated = UndanganVendor::where('email', 'gula@manis.test')->first();
        $this->assertEquals('PT Gula Manis Nusantara', $updated->nama_perusahaan);
        $this->assertEquals('Pesan diperbarui.', $updated->pesan);
        $this->assertNotEquals($firstToken, $updated->token);
    }

    public function test_invited_vendor_can_open_registration_page_with_invitation_token(): void
    {
        $invitation = UndanganVendor::create([
            'nama_perusahaan' => 'PT Jagung Makmur',
            'email' => 'jagung@makmur.test',
            'kategori_komoditas' => 'Beras & Biji-bijian',
            'token' => Str::random(40),
            'status' => 'pending',
            'invited_by' => $this->superadmin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->get('/register/vendor?invitation='.$invitation->token);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/RegisterVendor')
            ->has('invitation', fn ($inv) => $inv
                ->where('token', $invitation->token)
                ->where('nama_perusahaan', 'PT Jagung Makmur')
                ->where('email_kontak', 'jagung@makmur.test')
                ->where('kategori_komoditas', 'Beras & Biji-bijian')
            )
        );
    }

    public function test_registering_vendor_with_invitation_token_marks_invitation_as_registered(): void
    {
        Storage::fake('local');
        Mail::fake();

        $email = 'tebu@sejahtera.test';
        $invitation = UndanganVendor::create([
            'nama_perusahaan' => 'PT Tebu Sejahtera',
            'email' => $email,
            'kategori_komoditas' => 'Gula, Pemanis & Rempah',
            'token' => Str::random(40),
            'status' => 'pending',
            'invited_by' => $this->superadmin->id,
            'expires_at' => now()->addDays(7),
        ]);

        // Request OTP
        $otpResponse = $this->postJson('/register/vendor/otp', [
            'email_kontak' => $email,
        ]);
        $otpResponse->assertStatus(200);

        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($email, &$code) {
            $code = $mail->code;

            return $mail->hasTo($email);
        });

        // Submit vendor registration with invitation token
        $dummyPdf = UploadedFile::fake()->create('npwp.pdf', 500, 'application/pdf');

        $regResponse = $this->post('/register/vendor', [
            'nama_perusahaan' => 'PT Tebu Sejahtera',
            'email_kontak' => $email,
            'no_telepon' => '081234567890',
            'kategori_komoditas' => 'Gula, Pemanis & Rempah',
            'alamat' => 'Jl. Perkebunan Tebu No. 10',
            'otp' => $code,
            'dokumen_legalitas' => $dummyPdf,
            'invitation_token' => $invitation->token,
        ]);

        $regResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendor', [
            'nama_perusahaan' => 'PT Tebu Sejahtera',
            'email_kontak' => $email,
        ]);

        $invitation->refresh();
        $this->assertEquals('terdaftar', $invitation->status);
        $this->assertNotNull($invitation->registered_at);
    }
}
