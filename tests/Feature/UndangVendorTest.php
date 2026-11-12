<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusUndanganVendor;
use App\Mail\OtpMail;
use App\Mail\UndanganVendorMail;
use App\Models\CommodityCategory;
use App\Models\UndanganVendor;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\CommodityCategorySeeder;
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
        $this->seed(CommodityCategorySeeder::class);

        $this->superadmin = User::factory()->create([
            'role_id' => 1, // Superadmin
            'name' => 'Super Administrator',
            'email' => 'superadmin@pagora.test',
            'is_active' => true,
        ]);

        $this->manajemen = User::factory()->create([
            'role_id' => 5, // Manajemen
            'name' => 'Admin Manajemen',
            'email' => 'manajemen@pagora.test',
            'is_active' => true,
        ]);

        $this->buyer = User::factory()->create([
            'role_id' => 2, // Buyer
            'name' => 'Staff Buyer',
            'email' => 'buyer@pagora.test',
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_invite_a_new_vendor_and_email_is_sent(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'company_name' => 'PT Mitra Sawit Sejahtera',
                'email' => 'mitra@sawit.co.id',
                'kategori_komoditas' => 'Minyak & Lemak Nabati',
                'message' => 'Kami mengundang Anda untuk memasok bahan baku CPO.',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/master/vendor');

        $this->assertDatabaseHas('vendor_invitations', [
            'company_name' => 'PT Mitra Sawit Sejahtera',
            'email' => 'mitra@sawit.co.id',
            'kategori_komoditas' => 'Minyak & Lemak Nabati',
            'message' => 'Kami mengundang Anda untuk memasok bahan baku CPO.',
            'status' => 'pending',
            'invited_by' => $this->superadmin->id,
        ]);

        Mail::assertQueued(UndanganVendorMail::class, function (UndanganVendorMail $mail) {
            return $mail->hasTo('mitra@sawit.co.id')
                && $mail->invitation->company_name === 'PT Mitra Sawit Sejahtera'
                && str_contains($mail->registrationUrl, $mail->invitation->token);
        });
    }

    public function test_manajemen_can_invite_a_new_vendor(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->manajemen)
            ->post('/master/vendor/undang', [
                'company_name' => 'CV Beras Nusantara',
                'email' => 'kontak@berasnusantara.id',
                'kategori_komoditas' => 'Beras & Biji-bijian',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/master/vendor');

        $this->assertDatabaseHas('vendor_invitations', [
            'company_name' => 'CV Beras Nusantara',
            'email' => 'kontak@berasnusantara.id',
            'invited_by' => $this->manajemen->id,
        ]);

        Mail::assertQueued(UndanganVendorMail::class);
    }

    public function test_buyer_cannot_invite_a_vendor(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->buyer)
            ->post('/master/vendor/undang', [
                'company_name' => 'PT Terlarang',
                'email' => 'terlarang@vendor.com',
            ]);

        $response->assertStatus(403);
        Mail::assertNothingOutgoing();
    }

    public function test_guest_cannot_invite_a_vendor(): void
    {
        Mail::fake();

        $response = $this->post('/master/vendor/undang', [
            'company_name' => 'PT Anonim',
            'email' => 'anonim@vendor.com',
        ]);

        $response->assertRedirect('/login');
        Mail::assertNothingOutgoing();
    }

    public function test_validation_fails_when_fields_are_invalid(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'company_name' => '',
                'email' => 'not-an-email',
            ]);

        $response->assertSessionHasErrors(['company_name', 'email']);
        Mail::assertNothingOutgoing();
    }

    public function test_cannot_invite_vendor_with_already_registered_vendor_email(): void
    {
        Mail::fake();

        Vendor::create([
            'company_name' => 'PT Vendor Eksis',
            'slug' => 'pt-vendor-eksis-123456',
            'contact_email' => 'eksis@vendor.co.id',
            'phone' => '081234567890',
            'address' => 'Jl. Raya Industri No. 1',
            'kategori_komoditas' => 'Agroindustri & Pangan',
            'verification_status' => 'approved',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'company_name' => 'PT Vendor Lain',
                'email' => 'eksis@vendor.co.id',
            ]);

        $response->assertSessionHasErrors(['email']);
        Mail::assertNothingOutgoing();
    }

    public function test_cannot_invite_vendor_with_already_registered_user_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'company_name' => 'PT Akun Bentrok',
                'email' => $this->manajemen->email,
            ]);

        $response->assertSessionHasErrors(['email']);
        Mail::assertNothingOutgoing();
    }

    public function test_reinviting_vendor_updates_existing_pending_invitation(): void
    {
        Mail::fake();

        // First invitation
        $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'company_name' => 'PT Gula Manis',
                'email' => 'gula@manis.test',
                'kategori_komoditas' => 'Gula, Pemanis & Rempah',
            ]);

        $firstToken = UndanganVendor::where('email', 'gula@manis.test')->value('token');

        // Reinvite with updated notes
        $response = $this->actingAs($this->superadmin)
            ->post('/master/vendor/undang', [
                'company_name' => 'PT Gula Manis Nusantara',
                'email' => 'gula@manis.test',
                'kategori_komoditas' => 'Gula, Pemanis & Rempah',
                'message' => 'Pesan diperbarui.',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, UndanganVendor::where('email', 'gula@manis.test')->count());

        $updated = UndanganVendor::where('email', 'gula@manis.test')->first();
        $this->assertEquals('PT Gula Manis Nusantara', $updated->company_name);
        $this->assertEquals('Pesan diperbarui.', $updated->message);
        $this->assertNotEquals($firstToken, $updated->token);
    }

    public function test_invited_vendor_can_open_registration_page_with_invitation_token(): void
    {
        $invitation = UndanganVendor::create([
            'company_name' => 'PT Jagung Makmur',
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
                ->where('company_name', 'PT Jagung Makmur')
                ->where('contact_email', 'jagung@makmur.test')
                ->where('kategori_komoditas', 'Beras & Biji-bijian')
                ->etc()
            )
        );
    }

    public function test_opening_registration_page_with_expired_invitation_marks_it_as_expired(): void
    {
        $invitation = UndanganVendor::create([
            'company_name' => 'PT Kedaluwarsa',
            'email' => 'lama@kedaluwarsa.test',
            'token' => Str::random(40),
            'status' => StatusUndanganVendor::Pending,
            'invited_by' => $this->superadmin->id,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->get('/register/vendor?invitation='.$invitation->token);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/RegisterVendor')
            ->where('invitation', null)
        );
        $this->assertSame(StatusUndanganVendor::Expired, $invitation->fresh()->status);
    }

    public function test_registering_vendor_with_invitation_token_marks_invitation_as_registered(): void
    {
        Storage::fake('local');
        Mail::fake();

        $email = 'tebu@sejahtera.test';
        $invitation = UndanganVendor::create([
            'company_name' => 'PT Tebu Sejahtera',
            'email' => $email,
            'kategori_komoditas' => 'Gula, Pemanis & Rempah',
            'token' => Str::random(40),
            'status' => 'pending',
            'invited_by' => $this->superadmin->id,
            'expires_at' => now()->addDays(7),
        ]);

        // Request OTP
        $otpResponse = $this->postJson('/register/vendor/otp', [
            'contact_email' => $email,
        ]);
        $otpResponse->assertStatus(200);

        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $mail) use ($email, &$code) {
            $code = $mail->code;

            return $mail->hasTo($email);
        });

        // Submit vendor registration with invitation token
        $dummyPdf = UploadedFile::fake()->create('npwp.pdf', 500, 'application/pdf');
        $category = CommodityCategory::where('name', 'Gula & Pemanis')->first()
            ?? CommodityCategory::firstOrFail();

        $regResponse = $this->post('/register/vendor', [
            'name' => 'Budi Tebu',
            'email' => 'budi@sejahtera.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'PT Tebu Sejahtera',
            'tax_number' => '01.234.567.8-901.000',
            'contact_email' => $email,
            'phone' => '081234567890',
            'commodity_category_uuids' => [$category->uuid],
            'address' => 'Jl. Perkebunan Tebu No. 10',
            'otp' => $code,
            'legal_document' => $dummyPdf,
            'invitation_token' => $invitation->token,
        ]);

        $regResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendors', [
            'company_name' => 'PT Tebu Sejahtera',
            'contact_email' => $email,
        ]);

        $invitation->refresh();
        $this->assertSame(StatusUndanganVendor::Accepted, $invitation->status);
        $this->assertNotNull($invitation->registered_at);
    }
}
