<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KerjaSamaController;
use App\Http\Controllers\KomoditasController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PenerimaanBarangController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PengirimanController;
use App\Http\Controllers\PerusahaanController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RoleAksesController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorRegistrationController;
use App\Http\Controllers\VerifikasiDokumenController;
use App\Http\Controllers\VerifikasiTenantController;
use App\Models\DokumenLegalitasVendor;
use App\Models\Komoditas;
use App\Models\PenerimaanBarang;
use App\Models\Pengaturan;
use App\Models\Pengiriman;
use App\Models\PerusahaanBuyer;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Route;

// Public Home
Route::get('/', [HomeController::class, 'index'])->name('home');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'storeAuth'])->name('login.store');

    // Account activation OTP (first login of a new employee)
    Route::post('/login/otp', [AuthController::class, 'verifyLoginOtp'])->middleware('throttle:10,1')->name('login.otp.verify');
    Route::post('/login/otp/resend', [AuthController::class, 'resendLoginOtp'])->middleware('throttle:6,1')->name('login.otp.resend');
    Route::delete('/login/otp', [AuthController::class, 'cancelLoginOtp'])->name('login.otp.cancel');

    Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'storeForgotPassword'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'storeResetPassword'])->middleware('throttle:6,1')->name('password.update');

    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'storeRegister'])->name('register.store');

    // Vendor Candidate Registration
    Route::prefix('register/vendor')->name('register.vendor')->group(function () {
        Route::get('/', [VendorRegistrationController::class, 'create']);
        Route::post('/', [VendorRegistrationController::class, 'store'])->name('.store');
        Route::post('/otp', [VendorRegistrationController::class, 'sendOtp'])->middleware('throttle:6,1')->name('.otp');
    });

    Route::redirect('/register-vendor', '/register/vendor');

    Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Profil Saya Routes (every signed-in user, own account only)
    Route::controller(ProfilController::class)
        ->prefix('profil')
        ->name('profil.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::put('/', 'update')->name('update');
            Route::put('/password', 'updatePassword')->middleware('throttle:6,1')->name('password.update');
        });

    // Master Komoditas Routes
    Route::controller(KomoditasController::class)
        ->prefix('master/komoditas')
        ->name('master.komoditas.')
        ->group(function () {
            Route::get('/', 'index')->can('viewAny', Komoditas::class)->name('index');
            Route::get('/create', 'create')->can('create', Komoditas::class)->name('create');
            Route::post('/', 'store')->can('create', Komoditas::class)->name('store');
            Route::get('/{uuid}/edit', 'edit')->whereUuid('uuid')->can('viewAny', Komoditas::class)->name('edit');
            Route::put('/{uuid}', 'update')->whereUuid('uuid')->can('viewAny', Komoditas::class)->name('update');
            Route::delete('/{uuid}', 'destroy')->whereUuid('uuid')->can('viewAny', Komoditas::class)->name('destroy');
        });

    // Purchase Order & Negosiasi Routes
    Route::controller(PurchaseOrderController::class)
        ->prefix('purchase-order')
        ->name('purchase-order.')
        ->group(function () {
            Route::get('/', 'index')->can('viewAny', PurchaseOrder::class)->name('index');
            Route::get('/create', 'create')->can('create', PurchaseOrder::class)->name('create');
            Route::post('/', 'store')->can('create', PurchaseOrder::class)->name('store');
            Route::get('/{uuid}', 'show')->whereUuid('uuid')->can('viewAny', PurchaseOrder::class)->name('show');
            Route::get('/{uuid}/edit', 'edit')->whereUuid('uuid')->can('create', PurchaseOrder::class)->name('edit');
            Route::put('/{uuid}', 'update')->whereUuid('uuid')->name('update');
            Route::delete('/{uuid}', 'destroy')->whereUuid('uuid')->name('destroy');
            Route::post('/{uuid}/konfirmasi', 'konfirmasi')->whereUuid('uuid')->name('konfirmasi');
            Route::get('/{uuid}/negosiasi', 'negosiasi')->whereUuid('uuid')->can('viewAny', PurchaseOrder::class)->name('negosiasi');
            Route::post('/{uuid}/tawar', 'tawar')->whereUuid('uuid')->name('tawar');
            Route::post('/{uuid}/setujui', 'setujui')->whereUuid('uuid')->name('setujui');
            Route::post('/{uuid}/batalkan', 'batalkan')->whereUuid('uuid')->name('batalkan');
        });

    // Pengiriman Logistik (Self-Reporting Logistics — UC-14)
    Route::controller(PengirimanController::class)
        ->prefix('pengiriman')
        ->name('pengiriman.')
        ->group(function () {
            Route::get('/', 'index')->can('viewAny', Pengiriman::class)->name('index');
            Route::get('/create', 'create')->can('create', Pengiriman::class)->name('create');
            Route::post('/', 'store')->can('create', Pengiriman::class)->name('store');
            Route::get('/{uuid}', 'show')->whereUuid('uuid')->can('viewAny', Pengiriman::class)->name('show');
        });

    // Inspeksi Barang QC Gudang (Goods Receipt — UC-15)
    Route::controller(PenerimaanBarangController::class)
        ->prefix('inspeksi-qc')
        ->name('inspeksi-qc.')
        ->group(function () {
            Route::get('/', 'index')->can('viewAny', PenerimaanBarang::class)->name('index');
            Route::get('/create', 'create')->can('create', PenerimaanBarang::class)->name('create');
            Route::post('/', 'store')->can('create', PenerimaanBarang::class)->name('store');
            Route::get('/item/{itemUuid}/foto/{index}', 'photo')
                ->whereUuid('itemUuid')
                ->whereNumber('index')
                ->can('viewAny', PenerimaanBarang::class)
                ->name('photo');
            Route::get('/{uuid}', 'show')->whereUuid('uuid')->can('viewAny', PenerimaanBarang::class)->name('show');
        });

    // Manajemen Perusahaan (Tenant Owner & Vendor manage their company profile)
    Route::controller(PerusahaanController::class)
        ->prefix('manajemen/perusahaan')
        ->name('manajemen.perusahaan.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::put('/', 'update')->name('update');
        });

    // Manajemen Pengguna (Internal Perusahaan) Routes
    Route::controller(PegawaiController::class)
        ->prefix('manajemen/pengguna')
        ->name('manajemen.pengguna.')
        ->middleware('can:viewAny,'.User::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{uuid}', 'show')->whereUuid('uuid')->name('show');
            Route::put('/{uuid}', 'update')->whereUuid('uuid')->name('update');
            Route::delete('/{uuid}', 'destroy')->whereUuid('uuid')->name('destroy');
        });

    Route::redirect('/manajemen/internal-perusahaan', '/manajemen/pengguna');

    // Role & Hak Akses (6 role tetap; hanya permission & deskripsi yang diatur)
    Route::controller(RoleAksesController::class)
        ->prefix('manajemen/role-akses')
        ->name('manajemen.role-akses.')
        ->middleware('can:viewAny,'.Role::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::put('/{role}', 'update')->whereIn('role', array_column(RoleName::cases(), 'value'))->name('update');
        });

    // Master Vendor & Pemblokiran Rekanan
    Route::controller(VendorController::class)
        ->prefix('master/vendor')
        ->name('master.vendor.')
        ->group(function () {
            Route::get('/', 'index')->can('viewAny', Vendor::class)->name('index');
            Route::post('/undang', 'undang')->name('undang');
            Route::post('/{uuid}/blokir', 'blokir')->whereUuid('uuid')->name('blokir');
            Route::post('/{uuid}/buka-blokir', 'bukaBlokir')->whereUuid('uuid')->name('buka-blokir');
        });

    // Master Vendor Legal Document Verification Routes (Superadmin Only)
    Route::middleware(['superadmin', 'can:viewAny,'.DokumenLegalitasVendor::class])
        ->prefix('master/vendor/verifikasi-dokumen')
        ->name('master.vendor.verifikasi-dokumen.')
        ->controller(VerifikasiDokumenController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');

            Route::post('/{uuid}/verifikasi', 'verify')
                ->whereUuid('uuid')
                ->name('verify');

            Route::get('/{uuid}/file', 'download')
                ->whereUuid('uuid')
                ->name('download');
        });

    // Master Tenant Legal Document Verification Routes (Superadmin Only)
    Route::middleware(['superadmin', 'can:viewAny,'.PerusahaanBuyer::class])
        ->prefix('master/tenant/verifikasi-dokumen')
        ->name('master.tenant.verifikasi-dokumen.')
        ->controller(VerifikasiTenantController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');

            Route::post('/{uuid}/verifikasi', 'verify')
                ->whereUuid('uuid')
                ->name('verify');

            Route::get('/{uuid}/file', 'download')
                ->whereUuid('uuid')
                ->name('download');
        });

    Route::redirect('/superadmin/verifikasi-dokumen', '/master/vendor/verifikasi-dokumen');

    // Kerja Sama Vendor Routes
    Route::controller(KerjaSamaController::class)
        ->prefix('kerja-sama')
        ->name('kerja-sama.')
        ->group(function () {
            Route::get('/calon-mitra', 'calonMitraIndex')->name('calon-mitra.index');

            Route::get('/calon-mitra/{uuid}', 'calonMitraShow')
                ->whereUuid('uuid')
                ->name('calon-mitra.show');

            Route::post('/calon-mitra/{uuid}/ajukan', 'ajukanKerjaSama')
                ->whereUuid('uuid')
                ->name('calon-mitra.ajukan');
        });

    // Pengaturan Aplikasi Routes (Superadmin Only)
    Route::middleware(['superadmin', 'can:viewAny,'.Pengaturan::class])
        ->prefix('pengaturan')
        ->name('pengaturan.')
        ->controller(PengaturanController::class)
        ->group(function () {
            Route::redirect('/', '/pengaturan/profil')->name('index');
            Route::get('/profil', 'profil')->name('profil');
            Route::post('/profil', 'updateProfil')->name('profil.update');
            Route::get('/integrasi', 'integrasi')->name('integrasi');
            Route::put('/integrasi', 'updateIntegrasi')->name('integrasi.update');
        });

    // Notifikasi Routes
    Route::controller(NotificationController::class)
        ->group(function () {
            Route::get('/notifikasi', 'index')->name('notifications.index');
            Route::get('/notifications', 'apiIndex')->name('notifications.api.index');
            Route::get('/notifications/unread-count', 'unreadCount')->name('notifications.unread-count');
            Route::post('/notifications/read-all', 'markAllAsRead')->name('notifications.read-all');
            Route::post('/notifications/{uuid}/read', 'markAsRead')->whereUuid('uuid')->name('notifications.read');
        });

    // Kerja Sama Vendor Routes
    Route::controller(KerjaSamaController::class)
        ->prefix('kerja-sama')
        ->name('kerja-sama.')
        ->group(function () {
            // Feature 4: Vendor mengajukan kerja sama kepada Buyer
            Route::get('/calon-mitra', 'calonMitraIndex')->name('calon-mitra.index');

            Route::get('/calon-mitra/{uuid}', 'calonMitraShow')
                ->whereUuid('uuid')
                ->name('calon-mitra.show');

            Route::post('/calon-mitra/{uuid}/ajukan', 'ajukanKerjaSama')
                ->whereUuid('uuid')
                ->name('calon-mitra.ajukan');

            // Feature 5: Vendor menanggapi undangan kerja sama dari Buyer
            Route::get('/undangan-masuk', 'incomingInvitations')
                ->name('undangan-masuk.index');

            Route::get('/undangan-masuk/{uuid}', 'showInvitation')
                ->whereUuid('uuid')
                ->name('undangan-masuk.show');

            Route::post('/undangan-masuk/{uuid}/tanggapi', 'respondInvitation')
                ->whereUuid('uuid')
                ->name('undangan-masuk.respond');
        });

});
