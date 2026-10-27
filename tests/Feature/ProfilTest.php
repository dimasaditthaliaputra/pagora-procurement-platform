<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfilTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->user = User::factory()->create([
            'role_id' => 2, // Buyer
            'nama_lengkap' => 'Staff Buyer',
            'email' => 'buyer@pagora.test',
            'password' => 'password-lama',
            'status_aktif' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/profil')->assertRedirect('/login');
        $this->put('/profil', ['nama_lengkap' => 'X'])->assertRedirect('/login');
        $this->put('/profil/password', [])->assertRedirect('/login');
    }

    public function test_profil_page_shows_own_data_without_internal_ids(): void
    {
        $response = $this->actingAs($this->user)->get('/profil');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Profil/Index')
            ->where('profil.data.uuid', $this->user->uuid)
            ->where('profil.data.nama_lengkap', 'Staff Buyer')
            ->where('profil.data.email', 'buyer@pagora.test')
            ->where('profil.data.role', 'Buyer')
            ->where('profil.data.punya_password', true)
            ->missing('profil.data.id')
            ->missing('profil.data.password'));
    }

    public function test_user_can_update_name_and_avatar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user)
            ->post('/profil', [
                '_method' => 'put',
                'nama_lengkap' => 'Nama Baru',
                'avatar' => UploadedFile::fake()->image('foto.jpg', 300, 300),
            ])
            ->assertRedirect('/profil')
            ->assertSessionHas('success');

        $this->user->refresh();
        $this->assertSame('Nama Baru', $this->user->nama_lengkap);
        Storage::disk('public')->assertExists($this->user->avatar);
        $this->assertSame(Storage::disk('public')->url($this->user->avatar), $this->user->avatar_url);
    }

    public function test_email_and_role_cannot_be_changed_from_profil(): void
    {
        $this->actingAs($this->user)->put('/profil', [
            'nama_lengkap' => 'Nama Baru',
            'email' => 'ganti@pagora.test',
            'role_id' => 1,
            'status_aktif' => false,
        ]);

        $this->user->refresh();
        $this->assertSame('buyer@pagora.test', $this->user->email);
        $this->assertSame(2, $this->user->role_id);
        $this->assertTrue($this->user->status_aktif);
    }

    public function test_replacing_and_removing_avatar_deletes_old_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user)->put('/profil', [
            'nama_lengkap' => 'Staff Buyer',
            'avatar' => UploadedFile::fake()->image('lama.png'),
        ]);
        $lama = $this->user->refresh()->avatar;

        $this->put('/profil', [
            'nama_lengkap' => 'Staff Buyer',
            'avatar' => UploadedFile::fake()->image('baru.png'),
        ]);
        $baru = $this->user->refresh()->avatar;

        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($baru);

        $this->put('/profil', ['nama_lengkap' => 'Staff Buyer', 'hapus_avatar' => true]);

        $this->assertNull($this->user->refresh()->avatar);
        Storage::disk('public')->assertMissing($baru);
    }

    public function test_google_avatar_url_is_kept_as_is_and_never_deleted_from_storage(): void
    {
        Storage::fake('public');
        $this->user->update(['avatar' => 'https://lh3.googleusercontent.com/a/foto']);

        $this->assertSame('https://lh3.googleusercontent.com/a/foto', $this->user->avatar_url);

        $this->actingAs($this->user)
            ->put('/profil', ['nama_lengkap' => 'Staff Buyer', 'hapus_avatar' => true])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->user->refresh()->avatar);
    }

    public function test_profil_validation(): void
    {
        $this->actingAs($this->user)
            ->put('/profil', [
                'nama_lengkap' => '',
                'avatar' => UploadedFile::fake()->create('foto.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['nama_lengkap', 'avatar']);
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $this->actingAs($this->user)
            ->put('/profil/password', [
                'password_saat_ini' => 'password-lama',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ])
            ->assertRedirect('/profil')
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('password-baru-123', $this->user->refresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->put('/profil/password', [
                'password_saat_ini' => 'salah',
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ])
            ->assertSessionHasErrors(['password_saat_ini']);

        $this->assertTrue(Hash::check('password-lama', $this->user->refresh()->password));
    }

    public function test_password_confirmation_and_length_are_validated(): void
    {
        $this->actingAs($this->user)
            ->put('/profil/password', [
                'password_saat_ini' => 'password-lama',
                'password' => 'pendek',
                'password_confirmation' => 'beda',
            ])
            ->assertSessionHasErrors(['password']);
    }

    public function test_google_user_without_password_can_set_one(): void
    {
        $googleUser = User::factory()->create([
            'role_id' => 2,
            'password' => null,
            'google_id' => 'google-123',
            'status_aktif' => true,
        ]);

        $this->actingAs($googleUser)
            ->get('/profil')
            ->assertInertia(fn (Assert $page) => $page->where('profil.data.punya_password', false));

        $this->put('/profil/password', [
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password-baru-123', $googleUser->refresh()->password));
    }
}
