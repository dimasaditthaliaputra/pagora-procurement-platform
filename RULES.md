# RULES.md — Standar Penulisan Kode Project PAGORA

> Aturan tetap untuk semua developer dan AI agent yang mengerjakan project ini.
> Baca seluruh dokumen ini sebelum menulis kode. Pemilik project hanya menjelaskan **flow/fitur**; keputusan teknis (struktur, pola, penamaan, validasi, otorisasi, response) mengikuti dokumen ini tanpa perlu ditanyakan ulang.

---

## 0. Sumber Kebenaran (Baca Dulu)

| Urutan | Dokumen | Isi | Bila bertentangan |
|---|---|---|---|
| 1 | `PRD.md` | Fitur, aktor & hak akses, use case, aturan bisnis (BR-xx), status, model data target | **Paling berlaku** |
| 2 | `design-db.md` | Detail skema: tabel, kolom, tipe, index, urutan migration, seeder | Kalah dari PRD |
| 3 | `RULES.md` (dokumen ini) | Cara menulis kode | Kalah dari PRD untuk hal bisnis; menang untuk hal teknis |
| 4 | Kode yang sudah ada | Contoh nyata pola di atas | Ikuti RULES bila kode lama melanggar |

- Jangan mengarang fitur, field, status, atau role di luar `PRD.md`. Bila sesuatu tidak ada di PRD, tanyakan ke user.
- Modul referensi untuk pola CRUD: **Komoditas** (`KomoditasController`, `KomoditasService`, `KomoditasRepository`, `KomoditasResource`, `Requests/Komoditas/*`, `Pages/Master/Komoditas/*`, `tests/Feature/KomoditasTest.php`). Tiru strukturnya saat membuat modul baru.

---

## 1. Stack & Konteks

- **Backend:** Laravel 13 (PHP 8.5), Laravel Fortify (auth email), Laravel Socialite (Google OAuth), PostgreSQL
- **Frontend:** Inertia.js v3 + React + Vite, **JavaScript (`.jsx`/`.js`), bukan TypeScript**, satu project (monolith)
- **UI:** shadcn/ui (`resources/js/Components/ui`) + Tailwind CSS
- **Komponen data & input:** Data Table (`@tanstack/react-table`), Combobox (Popover + Command), `react-dropzone` (bagian 8.5–8.10)
- **Aset suara:** `public/assets/sounds/success.mp3` dan `public/assets/sounds/failure.mp3` (sudah disediakan, jangan dibuat/diganti)
- **Arsitektur backend:** MVC + Service Repository Pattern + Laravel Policy
- **Arsitektur aplikasi:** **multi-tenant** (banyak perusahaan pembeli/Tenant + Vendor dalam satu instansi; lihat bagian 9.3)
- **Bahasa** (lihat bagian 11.1 untuk rincian):
  - Teks untuk user (UI, pesan validasi, flash, email) → **Bahasa Indonesia**.
  - Nama tabel, kolom, nilai enum di database → **Bahasa Inggris**.
  - Method, variabel, komentar kode → **Bahasa Inggris**.
  - Nama class/file domain yang sudah ada → **tetap** (mis. `Komoditas`, `Pegawai`, `Sengketa`); jangan diganti.
  - Nama class untuk entitas/tabel **baru** → **Bahasa Inggris** (mis. `CreditNote`, `Payment`, `PurchaseReturn`).

---

## 2. Cara Kerja Agent (Wajib Diikuti)

Setiap menerima tugas:

1. **Baca `PRD.md`** bagian yang relevan (use case, FR, BR, status) dan `design-db.md` bila menyentuh database.
2. **Pahami flow** yang dijelaskan user. Bila ada hal ambigu yang tidak dijawab PRD/RULES, tulis asumsi di awal jawaban lalu lanjutkan; bila menyangkut aturan bisnis, hak akses, atau skema, **tanyakan dulu**.
3. **Periksa kode yang sudah ada** (model, migration, policy, route, komponen) sebelum membuat file baru. Gunakan ulang; jangan membuat duplikat.
4. **Kerjakan berurutan:** Migration → Model (+ Enum) → Policy → Repository → Service → Form Request → Controller → Route → Frontend → Test.
5. **Jangan mengubah file yang tidak berkaitan** dengan tugas.
6. **Jangan install package baru** tanpa persetujuan user. Pengecualian: `@tanstack/react-table`, `react-dropzone`, dan komponen shadcn pendukung (bagian 8.5–8.8) sudah disetujui; cek `package.json` dulu.
7. Setelah selesai, berikan ringkasan sesuai bagian 15.

---

## 3. Struktur Folder

### 3.1 Backend

```
app/
├── Enums/                      # PHP Backed Enum untuk status (bagian 9.2)
├── Helpers/
│   └── ResponseHelper.php
├── Http/
│   ├── Controllers/            # Controller tipis, 1 controller per domain/fitur
│   ├── Middleware/
│   ├── Resources/              # API Resource (field eksplisit, tanpa id)
│   └── Requests/{Domain}/      # Form Request per domain, mis. Requests/Komoditas/
├── Models/
├── Policies/                   # Laravel Policy, 1 per model (bagian 4.5)
├── Providers/
│   └── RepositoryServiceProvider.php
├── Repositories/
│   ├── Contracts/              # Interface: {Model}RepositoryInterface
│   └── Eloquent/               # Implementasi: {Model}Repository
└── Services/                   # Logika bisnis: {Domain}Service
```

Aturan penamaan file:
- Controller: `{Domain}Controller` (contoh: `KomoditasController`, `PurchaseOrderController`)
- Service: `{Domain}Service`
- Policy: `{Model}Policy` (contoh: `KomoditasPolicy` untuk model `Komoditas`)
- Repository: interface `{Model}RepositoryInterface`, implementasi `{Model}Repository`
- Form Request: `{Action}{Model}Request` di folder domain (contoh: `Komoditas/StoreKomoditasRequest`, `Vendor/BlokirVendorRequest`)
- Enum: `Status{Domain}` atau nama konsep (contoh: `StatusPurchaseOrder`)

### 3.2 Frontend

Struktur yang **sudah dipakai** (ikuti persis, perhatikan huruf besar/kecil):

```
resources/js/
├── app.jsx
├── Pages/                          # Halaman Inertia, folder & file PascalCase
│   ├── Auth/Login.jsx
│   ├── Master/Komoditas/
│   │   ├── Index.jsx
│   │   ├── Create.jsx
│   │   ├── Edit.jsx
│   │   ├── KomoditasForm.jsx       # form dipakai Create & Edit
│   │   └── columns.jsx             # definisi kolom Data Table
│   └── PurchaseOrder/components/   # komponen khusus modul (PascalCase .jsx)
├── Components/
│   ├── ui/                         # komponen shadcn (jangan diedit tanpa alasan kuat)
│   ├── data-table/                 # data-table.jsx, data-table-pagination.jsx, data-table-column-header.jsx
│   ├── combobox.jsx
│   ├── file-dropzone.jsx
│   ├── loading-button.jsx
│   ├── flash-handler.jsx
│   └── AppSidebar.jsx ...          # komponen aplikasi (PascalCase)
├── Layouts/DashboardLayout.jsx
├── hooks/use-*.js
└── lib/                            # utils.js (cn), sound.js, notify.js, format.js
```

- Render halaman: `Inertia::render('Master/Komoditas/Index')` (path sesuai folder `Pages`, PascalCase).
- Import memakai alias `@/` (contoh: `@/Components/loading-button`, `@/lib/notify`).
- Komponen reusable generik (building block) memakai nama file kebab-case (`combobox.jsx`); komponen halaman/aplikasi memakai PascalCase (`HapusDrafDialog.jsx`).

---

## 4. Arsitektur Backend: MVC + Service Repository + Policy

### 4.1 Alur & Tanggung Jawab

```
Route → Controller → (Form Request + Policy) → Service → Repository (Interface) → Model
```

| Layer | Boleh | Dilarang |
|---|---|---|
| **Route** | Memetakan URL ke method controller, memasang middleware | `return view()`, `Inertia::render()`, closure berisi logika |
| **Controller** | Terima request, panggil Service, kembalikan response lewat `ResponseHelper` / `Inertia::render` | Query Eloquent, logika bisnis, validasi inline, **pengecekan role/izin** |
| **Form Request** | Aturan validasi + pesan Bahasa Indonesia, `authorize()` yang memanggil **Policy** | Logika bisnis, cek role manual |
| **Policy** | Keputusan izin per aksi (role, kepemilikan Tenant/Vendor, status data) | Query berat, mengubah data |
| **Service** | Logika bisnis, transisi status, orkestrasi repository, transaksi DB, otorisasi aksi per data lewat Policy, melempar exception | Akses `Request`, return response HTTP, query Eloquent langsung |
| **Repository** | Akses data (query Eloquent), **scoping Tenant/Vendor** | Logika bisnis, return response HTTP |
| **Model** | Relasi, casts (termasuk enum), fillable, scope, accessor, `$table` | Logika bisnis kompleks |

### 4.2 Aturan Dependency

- Controller meng-inject **Service** lewat constructor.
- Service meng-inject **Repository Interface** (bukan class konkret) lewat constructor.
- Binding interface → implementasi di `RepositoryServiceProvider` (terdaftar di `bootstrap/providers.php`).
- Satu Repository untuk satu Model. Satu Service untuk satu domain; boleh memanggil Service lain.

### 4.3 Template Kode

**Repository Interface**

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findByUuid(string $uuid): ?User;

    public function create(array $data): User;
}
```

**Repository Implementation**

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly User $model) {}

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function findByUuid(string $uuid): ?User
    {
        return $this->model->where('uuid', $uuid)->first();
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }
}
```

**Service**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function login(array $credentials, bool $remember): void
    {
        $user = $this->users->findByEmail($credentials['email']);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Email belum terdaftar.',
            ]);
        }

        // ... Auth::attempt(...), session()->regenerate()
    }
}
```

**Controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function storeAuth(LoginRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->authService->login(
                $request->only('email', 'password'),
                $request->boolean('remember')
            );

            return ResponseHelper::success('Login berhasil.', redirectTo: route('dashboard'));
        } catch (ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError($e);
        }
    }
}
```

### 4.4 Controller Global per Domain

- Satu controller per domain/fitur. Untuk autentikasi: **satu `AuthController`** untuk semua tipe auth (email & Google).
- Method yang me-render halaman dan method yang memproses aksi dipisah (`login` vs `storeAuth`).
- Controller yang sudah tidak terpakai **harus dihapus** beserta route-nya.

### 4.5 Otorisasi dengan Laravel Policy (WAJIB)

Semua keputusan izin ("siapa boleh melakukan apa terhadap data apa") ditulis di **Policy**. Hak akses per aktor mengikuti tabel aktor di `PRD.md` §3.

**Prinsip**
- Satu Policy per model di `app/Policies/{Model}Policy.php` (terdeteksi otomatis lewat konvensi nama Laravel).
- **Dilarang** cek role/izin manual di Controller (`if (! $user->isOwner()) ...`) atau di JSX. Helper di `User` (`isSuperadmin()`, `isOwner()`, `isManajemen()`, `isVendor()`, dst.) **hanya dipakai di dalam Policy**.
- Policy memeriksa tiga hal bila relevan: **role**, **kepemilikan** (data milik Tenant/Vendor user), dan **status data** (mis. PO hanya bisa diubah saat `draft`).
- **Tidak ada `before()` yang meloloskan semua aksi untuk Superadmin.** Superadmin mengelola platform, bukan bertransaksi atas nama Tenant (PRD §3); izinnya ditulis eksplisit per ability.
- Ability standar: `viewAny`, `view`, `create`, `update`, `delete`. Aksi khusus memakai nama method camelCase yang jelas: `confirm`, `cancel`, `block`, `unblock`, `inspect`, `resolve`, dst.
- Middleware route (mis. `superadmin`) boleh dipakai untuk membatasi grup route secara kasar; Policy tetap wajib untuk setiap aksi.

**Di mana Policy dipanggil**

| Jenis aksi | Tempat | Cara |
|---|---|---|
| Tanpa data spesifik (`viewAny`, `create`) | `authorize()` di Form Request | `$this->user()?->can('create', Komoditas::class) ?? false` |
| Terhadap satu data (`view`, `update`, `delete`, aksi khusus) | Service, setelah data diambil lewat Repository | `Gate::forUser($actor)->authorize('update', $komoditas)` |

Alasan: route memakai `uuid` dan **tidak** memakai implicit route model binding (bagian 5.1), sehingga instance model baru tersedia di Service.

```php
// app/Policies/PurchaseOrderPolicy.php
class PurchaseOrderPolicy
{
    public function create(User $user): bool
    {
        return $user->isOwner() || $user->isPurchasing();
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return ($user->isOwner() || $user->isPurchasing())
            && $user->tenant_id === $purchaseOrder->tenant_id
            && $purchaseOrder->status === StatusPurchaseOrder::Draft;
    }
}

// Form Request
public function authorize(): bool
{
    return $this->user()?->can('create', PurchaseOrder::class) ?? false;
}

// Service
public function update(User $actor, string $uuid, array $data): PurchaseOrder
{
    $purchaseOrder = $this->findByUuid($actor, $uuid); // sudah di-scope Tenant (bagian 9.3)
    Gate::forUser($actor)->authorize('update', $purchaseOrder);

    // ... ubah data
}

// Controller
} catch (AuthorizationException $e) {
    return ResponseHelper::forbidden('Anda tidak memiliki akses untuk aksi ini.');
}
```

**Izin ke frontend** — jangan menghitung ulang role di JSX. Kirim flag izin dari backend (dihitung lewat Policy):

```php
// Resource
'can' => [
    'update' => $request->user()?->can('update', $this->resource) ?? false,
    'delete' => $request->user()?->can('delete', $this->resource) ?? false,
],

// Controller (izin level halaman)
'can' => ['create' => $request->user()->can('create', PurchaseOrder::class)],
```

```jsx
{purchaseOrder.can.update && <Button onClick={...}>Ubah</Button>}
```

---

## 5. Routes

- Semua route **wajib** menunjuk ke method controller:
  ```php
  Route::get('/login', [AuthController::class, 'login'])->name('login');
  ```
- **Dilarang** di `routes/*.php`: `return view(...)`, `Inertia::render(...)`, `Route::inertia(...)`, atau closure berisi logika.
- Beri `name()` pada setiap route. Kelompokkan dengan `Route::middleware(...)->group(...)` dan `Route::controller(...)->group(...)`.
- Middleware: `guest` untuk login/register/Google/reset kata sandi, `auth` untuk halaman terproteksi.
- Route bawaan Fortify diabaikan (`Fortify::ignoreRoutes()`); tetap manfaatkan action, rate limiter, dan pipeline Fortify.
- **Parameter route resource WAJIB memakai `uuid` atau `slug`, BUKAN `id`.** Lihat bagian 5.1.

### 5.1 Identifier Publik: UUID / Slug (WAJIB)

Tujuan: mencegah enumerasi data dan IDOR, serta tidak membocorkan jumlah/urutan data.

**Prinsip**
- Primary key `id` (auto-increment) **hanya dipakai internal backend**: relasi/foreign key, join, query.
- `id` **tidak boleh keluar ke frontend** dalam bentuk apa pun: URL, props Inertia, JSON, field form (termasuk hidden input), atribut HTML, `key`, query string.
- Identifier di luar backend: **`uuid`** (default semua entitas) atau **`slug`** (URL yang perlu mudah dibaca; `unique`).
- **UUID/slug bukan pengganti otorisasi.** Policy (bagian 4.5) dan scoping Tenant (bagian 9.3) tetap wajib.

**Migration**
```php
Schema::create('commodities', function (Blueprint $table) {
    $table->id();                               // internal only
    $table->uuid('uuid')->unique();             // identifier publik
    $table->string('slug')->unique();           // hanya jika dibutuhkan
    $table->string('name');
    $table->timestamps();
});
```
Untuk tabel yang sudah ada: buat migration baru (tambah `uuid` nullable → isi data lama → ubah jadi `unique` + not null). Jangan edit migration lama.

**Model**
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Komoditas extends Model
{
    use HasUuids;

    protected $table = 'commodities';           // wajib bila nama class ≠ nama tabel (bagian 11.1)

    protected $hidden = ['id'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
```
Foreign key (`*_id`) juga disembunyikan (`$hidden`) atau tidak diikutkan saat data dikirim ke frontend.

**Route** — gunakan constraint format:
```php
Route::controller(KomoditasController::class)
    ->prefix('komoditas')
    ->name('komoditas.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{uuid}', 'show')->whereUuid('uuid')->name('show');
        Route::put('/{uuid}', 'update')->whereUuid('uuid')->name('update');
        Route::delete('/{uuid}', 'destroy')->whereUuid('uuid')->name('destroy');
    });
```
- Hindari nama param yang menyiratkan `id` (`{id}`, `{komoditas_id}`).
- Controller menerima `string $uuid` lalu meneruskannya ke Service. **Tidak memakai implicit route model binding.**

**Repository & Service**
```php
// Repository
public function findByUuid(string $uuid): ?Komoditas
{
    return $this->model->where('uuid', $uuid)->first();
}

// Service
public function findByUuid(string $uuid): Komoditas
{
    return $this->komoditas->findByUuid($uuid)
        ?? throw new ModelNotFoundException('Data tidak ditemukan.');
}
```
- `ModelNotFoundException` ditangkap di Controller → `ResponseHelper::notFound()`.
- Data milik Tenant/Vendor lain diperlakukan sebagai **tidak ditemukan (404)**, agar tidak membocorkan keberadaan data.

**Controller**
```php
public function show(string $uuid): Response|JsonResponse|RedirectResponse
{
    try {
        $komoditas = $this->service->findForUser(auth()->user(), $uuid);

        return Inertia::render('Master/Komoditas/Show', [
            'komoditas' => new KomoditasResource($komoditas),
        ]);
    } catch (ModelNotFoundException) {
        return ResponseHelper::notFound('Data tidak ditemukan.');
    } catch (AuthorizationException) {
        return ResponseHelper::forbidden('Anda tidak memiliki akses untuk aksi ini.');
    } catch (Throwable $e) {
        return ResponseHelper::serverError($e);
    }
}
```

**Data ke Frontend** — jangan kirim model mentah. Gunakan **API Resource** dengan field eksplisit; nama key mengikuti nama kolom (Bahasa Inggris):
```php
class KomoditasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'name'          => $this->name,
            'category_uuid' => $this->category?->uuid,   // relasi lewat uuid
            'status'        => $this->status->value,
            'status_label'  => $this->status->label(),   // label Indonesia dari Enum
        ];
    }
}
```

**Input dari Frontend (relasi)**
- Frontend mengirim `category_uuid`, bukan `category_id`.
- Form Request: `'category_uuid' => ['required', 'uuid', 'exists:commodity_categories,uuid']`.
- **Service** menerjemahkan uuid → id lewat Repository sebelum menyimpan.
- Aksi massal mengirim array `uuid`, divalidasi `'uuids.*' => ['uuid']`.

**Frontend**
```jsx
<Link href={route('komoditas.show', item.uuid)}>{item.name}</Link>
router.delete(route('komoditas.destroy', item.uuid));
{items.map((item) => <Row key={item.uuid} item={item} />)}
```

---

## 6. Response Helper

Semua controller **wajib** memakai `App\Helpers\ResponseHelper`. Dilarang `response()->json(..., 200)` atau `abort(500)` langsung di controller.

### 6.1 Method

| Method | Status |
|---|---|
| `success()` | 200 |
| `created()` | 201 |
| `badRequest()` | 400 |
| `unauthorized()` | 401 |
| `forbidden()` | 403 |
| `notFound()` | 404 |
| `validationError()` | 422 |
| `serverError()` | 500 |

### 6.2 Format JSON

```json
{
  "success": true,
  "message": "Pesan untuk user",
  "data": null,
  "errors": null
}
```

### 6.3 Perilaku Inertia

- `request()->expectsJson()` → JSON dengan status code yang sesuai.
- Request Inertia biasa:
  - Sukses → `redirect()->intended($redirectTo)` atau `back()` + `->with('success', $message)`.
  - Gagal validasi → `back()->withErrors($errors)` (masuk ke props `errors`, tanpa flash `error`).
  - Gagal non-validasi (400/403/404/500) → `back()->with('error', $message)`.
  - Flash `success`/`error` dibagikan lewat `HandleInertiaRequests::share()` dan ditangani satu tempat oleh `flash-handler` (bagian 8.8).
- `serverError()` wajib `Log::error(...)` dan menampilkan pesan generik (jangan bocorkan exception, stack trace, query).

### 6.4 Exception → Response di Controller

| Exception | Response |
|---|---|
| `ValidationException` | `ResponseHelper::validationError($e->errors())` |
| `ModelNotFoundException` | `ResponseHelper::notFound(...)` |
| `AuthorizationException` | `ResponseHelper::forbidden(...)` |
| Pelanggaran aturan bisnis (mis. transisi status tidak sah) | `ValidationException::withMessages([...])` dari Service, atau `badRequest()` bila tidak terkait field |
| `Throwable` lain | `ResponseHelper::serverError($e)` |

---

## 7. Validasi

- **Backend:** wajib **Form Request** (`php artisan make:request {Domain}/{Action}{Model}Request`). Jangan validasi inline di controller.
- **Frontend:** validasi sebelum submit (required, format, panjang, konfirmasi password) untuk feedback cepat.
- Frontend **tidak menggantikan** backend: backend selalu memvalidasi ulang.
- Pesan error Bahasa Indonesia di method `messages()`; nama field ramah user di `attributes()`.
- Aturan `unique`/`exists` wajib di backend (contoh: `unique:users,email`). Untuk data milik Tenant, `exists` harus dibatasi Tenant (`Rule::exists('tenant_commodities', ...)->where('tenant_id', ...)`).
- Batas berkas mengikuti PRD: maksimal **5 MB** per berkas untuk dokumen legalitas dan foto bukti.

---

## 8. Aturan Frontend

### 8.1 Umum

- Halaman = file di `Pages/`, dirender dari controller via `Inertia::render('Folder/Nama')`.
- **Functional component + hooks**. Dilarang class component.
- Gunakan komponen **shadcn/ui yang sudah ada** di `Components/ui`. Jangan membuat ulang. Komponen shadcn yang belum ada: **tanyakan dulu**, kecuali yang dibutuhkan bagian 8.5–8.10 (`table`, `popover`, `command`, `dialog`, `alert-dialog`, `dropdown-menu`, `sonner`).
- Tailwind; hindari inline style. Gunakan `cn()` dari `@/lib/utils` untuk class kondisional.
- Tidak ada `console.log`, kode mati, atau import tak terpakai.
- Identifier data di frontend **hanya `uuid` / `slug`**.
- Izin tampilan (tombol Ubah/Hapus/aksi) memakai flag `can.*` dari backend (bagian 4.5), bukan cek role di JSX.
- Status ditampilkan memakai `status_label` dari Resource; jangan memetakan nilai enum ke teks Indonesia di frontend.
- **Harga Pagu, estimasi harga, dan data internal Tenant tidak boleh dikirim ke halaman Vendor** (PRD BR-02); filter di Resource/Controller, bukan disembunyikan di JSX.

### 8.2 Form (Inertia `useForm`)

- Selalu gunakan `useForm` dari `@inertiajs/react`.
- **Nilai awal form kosong** (`''`, `false`, `[]`). Dilarang data dummy atau kredensial contoh.
- Disable tombol submit saat `processing`.
- Panggil `clearErrors('field')` saat user mengubah input terkait.
- `<form>` wajib `noValidate`. **Dilarang atribut HTML `required`** (dan `pattern`, `minLength`, `maxLength`, dll.) untuk validasi; gunakan JavaScript + backend dan **class invalid** (bagian 8.3). Penanda wajib cukup `*` pada label.
- Setiap button async wajib punya state loading (bagian 8.10).

### 8.3 Tampilan Error (PENTING)

- Error validasi ditampilkan sebagai **state invalid pada input**, bukan alert/toast/`window.alert`.
- Setiap input yang error wajib:
  - `aria-invalid={!!errors.field}`
  - class invalid: `border-destructive focus-visible:ring-destructive`
  - pesan kecil di bawah input (`<p className="text-sm text-destructive">`)
- Berlaku juga untuk elemen non-input (mis. tombol Google dengan `errors.google`).
- Error validasi tetap tanpa toast, tetapi **sound failure tetap diputar** (bagian 8.8).
- Toast hanya untuk **sukses/info** dan error **non-validasi**, selalu lewat `notify` (bagian 8.8).

### 8.4 Contoh Pola Input

```jsx
<div className="space-y-2">
  <Label htmlFor="email">Email *</Label>
  <Input
    id="email"
    type="email"
    value={data.email}
    onChange={(e) => {
      setData('email', e.target.value);
      clearErrors('email');
    }}
    aria-invalid={!!errors.email}
    className={cn(errors.email && 'border-destructive focus-visible:ring-destructive')}
  />
  {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
</div>
```

### 8.5 Data Table (TanStack Table)

Semua halaman daftar data **wajib** memakai Data Table, bukan `<table>` manual.

**Aturan**
- Komponen generik `Components/data-table/` dipakai ulang di semua modul. Jangan disalin per modul.
- Definisi kolom di file terpisah per modul: `Pages/<Area>/<Modul>/columns.jsx`.
- **Server-side** pagination, sorting, filtering (`manualPagination`, `manualSorting`, `manualFiltering`). Dilarang memfilter seluruh data di browser.
- `getRowId: (row) => row.uuid`.
- Search memakai debounce (±400 ms); state tabel (page, search, sort, per_page, filter) di query string lewat `router.get` dengan `preserveState`, `preserveScroll`, `replace`, `only`.
- Tampilkan loading (skeleton/overlay) dan empty state ("Belum ada data").
- Kolom aksi memakai `DropdownMenu` atau tombol ikon, ditampilkan sesuai `row.can.*`; hapus selalu lewat `AlertDialog` dengan tombol loading.

**Frontend**
```jsx
const table = useReactTable({
  data,
  columns,
  getRowId: (row) => row.uuid,
  getCoreRowModel: getCoreRowModel(),
  manualPagination: true,
  manualSorting: true,
  manualFiltering: true,
  pageCount: meta.last_page,
});

router.get(
  route('komoditas.index'),
  { search, sort, direction, page, per_page },
  {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['komoditas', 'filters'],
    onStart: () => setLoading(true),
    onFinish: () => setLoading(false),
  },
);
```

**Backend** — Repository melakukan paginate dengan whitelist kolom sort dan scoping Tenant:
```php
private const SORTABLE = ['name', 'created_at'];

public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
{
    $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'created_at';
    $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

    return $this->model
        ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
        ->orderBy($sort, $direction)
        ->paginate(min($perPage, 100))
        ->withQueryString();
}
```
- Kolom sort **wajib whitelist**.
- `per_page` dibatasi (maks 100). Pencarian teks di PostgreSQL memakai `ilike`.

### 8.6 Combobox (Popover + Command)

Untuk select dengan banyak opsi atau perlu pencarian. Untuk 2–5 opsi statis cukup `Select`.

**Aturan**
- Pakai komponen reusable `Components/combobox.jsx`; jangan menulis ulang Popover + Command.
- **Value = `uuid`** (atau slug). Opsi berbentuk `{ value: uuid, label: string }`.
- Props: `options`, `value`, `onChange`, `placeholder`, `error`, `disabled`, `loading`, `clearable`.
- State invalid sama seperti input biasa.
- Opsi ≤ ±100 → lewat props Inertia. Opsi banyak → async search ke endpoint controller (JSON via `ResponseHelper`), debounce, batas hasil 20–50, loading di dalam list.
- Trigger berupa `Button type="button"`.

```jsx
<Combobox
  options={categories}
  value={data.category_uuid}
  onChange={(v) => {
    setData('category_uuid', v);
    clearErrors('category_uuid');
  }}
  placeholder="Pilih kategori"
  error={errors.category_uuid}
/>
```

### 8.7 File Upload (react-dropzone)

Semua input file memakai `Components/file-dropzone.jsx`. Dilarang `<input type="file">` polos.

**Frontend**
- `accept`, `maxSize`, `maxFiles` **harus sama** dengan Form Request.
- File ditolak → state invalid pada dropzone + pesan lewat `setError`.
- Tampilkan preview/nama + ukuran + tombol hapus. `URL.createObjectURL` **wajib** di-`revokeObjectURL`.
- Saat upload: dropzone `disabled`, tombol submit loading, progress dari `useForm`.
- Update dengan file dikirim lewat `post` + `_method: 'put'`.
- Saat edit, tampilkan file lama; bila dihapus user, kirim `remove_<field>: true`.

```jsx
const { getRootProps, getInputProps } = useDropzone({
  accept: { 'image/*': ['.jpg', '.jpeg', '.png', '.webp'] },
  maxSize: 5 * 1024 * 1024,
  maxFiles: 1,
  multiple: false,
  disabled: processing,
  onDropAccepted: (files) => {
    setData('photo', files[0]);
    clearErrors('photo');
  },
  onDropRejected: () => {
    setError('photo', 'File harus berupa gambar JPG/PNG/WEBP maksimal 5 MB.');
    notify.validationFailed();
  },
});
```

**Backend**
- Form Request: `'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']`.
- Penyimpanan di **Service** dengan nama file **di-generate server**. Dokumen legalitas & bukti sengketa disimpan di disk **non-publik** dan diunduh lewat route berotorisasi (Policy), bukan URL publik.
- Simpan path di database; kirim URL lewat Resource.
- Hapus file lama saat diganti — **kecuali** berkas bukti yang immutable (foto inspeksi QC, lampiran sengketa): tidak pernah dihapus (bagian 9.4).

### 8.8 Sound & Notifikasi

Setiap pesan **success** dan **error** wajib disertai suara (`success.mp3` / `failure.mp3`). File sudah disediakan; jangan dibuat/diganti.

**Aturan**
- Dilarang `new Audio(...)` atau `toast.*` langsung di komponen. Semua lewat `@/lib/sound` dan `@/lib/notify`.
- **Satu event = satu suara.**
- Sukses → `notify.success(message)`; error non-validasi → `notify.error(message)`; error validasi → `notify.validationFailed()` (hanya suara).
- Flash dari backend ditangani **satu tempat**: `Components/flash-handler.jsx` di layout. Komponen lain tidak membaca flash sendiri.
- Tangkap error `play()`; guard SSR (`typeof window === 'undefined'`).

```js
// lib/notify.js
import { toast } from 'sonner';
import { playSound } from '@/lib/sound';

export const notify = {
  success(message) {
    playSound('success');
    toast.success(message);
  },
  error(message) {
    playSound('failure');
    toast.error(message);
  },
  validationFailed() {
    playSound('failure');
  },
};
```
```jsx
post(route('komoditas.store'), {
  onError: () => notify.validationFailed(), // sukses ditangani flash-handler
});
```

### 8.9 Modal vs Form Page

| Kondisi | Bentuk |
|---|---|
| **≤ 4 field sederhana**, tanpa upload file | **Modal** (`Dialog`) di halaman index |
| **≥ 5 field**, ada upload, textarea panjang, repeater/dynamic row (mis. item PO), multi-step, atau layout kompleks | **Form page** (`Create.jsx` / `Edit.jsx` + `{Modul}Form.jsx`) |

Jika ragu, pilih **form page**.

**Aturan modal**
- Komponen form di `Pages/<Area>/<Modul>/components/` dengan pola `useForm` yang sama.
- Modal dipakai untuk create **dan** edit; edit membawa data dari baris tabel (berdasarkan `uuid`).
- Modal **tidak ditutup saat error**; tutup saat `onSuccess`, lalu `reset()` + `clearErrors()`.
- Saat `processing`, modal tidak bisa ditutup; semua tombol loading.
- Konfirmasi hapus/aksi kritikal memakai `AlertDialog`, bukan `window.confirm`.

**Aturan form page**
- Route lengkap: `create`, `store`, `edit` (berdasarkan `uuid`), `update`.
- Tombol "Batal"/"Kembali" dan simpan (loading). `Card` + grid responsif.

### 8.10 Loading Button & Invalid vs Required

- **Setiap button async** wajib `LoadingButton` dari `@/Components/loading-button` (`loading`, `loadingText`).
- Sumber state: `processing` dari `useForm`, state lokal untuk `router.*` (`onStart`/`onFinish`), atau state fetch.
- Saat loading, tombol lain di form/dialog yang sama ikut di-`disable`.
- Validasi frontend saat submit: `setError` per field gagal, hentikan submit, fokuskan field error pertama, panggil `notify.validationFailed()` **satu kali**.

```jsx
<LoadingButton type="submit" loading={processing} loadingText="Menyimpan...">
  Simpan
</LoadingButton>
```

---

## 9. Database & Domain

### 9.1 Umum

- Perubahan skema **selalu** lewat migration baru. Jangan edit migration lama yang sudah dijalankan.
- Nama tabel & kolom **Bahasa Inggris**, plural snake_case untuk tabel (`purchase_orders`, `credit_notes`), foreign key `{singular}_id` (`purchase_order_id`). Nama target ada di `PRD.md` §8 dan `design-db.md`.
- `foreignId()->constrained()` dengan aksi `onDelete` yang jelas (umumnya `restrict` untuk data transaksi).
- Index pada kolom yang sering di-query, kolom status, dan foreign key.
- `$fillable` + `casts()` pada Model. Nilai uang memakai `decimal(15, 2)` dan cast `decimal:2`; jangan `float`.
- Operasi yang mengubah lebih dari satu tabel dibungkus `DB::transaction()` di **Service**.
- Hindari N+1: eager loading (`with()`) di Repository.
- Setiap tabel entitas CRUD punya `uuid` (`unique`) dan, bila perlu, `slug`.
- Data transaksi (PO, invoice, penerimaan, pembayaran, sengketa) **tidak pernah di-hard delete**. Penghapusan entitas master/draf memakai `SoftDeletes`.

### 9.2 Status & Enum

- Kolom status bertipe `string` + **PHP Backed Enum** di `app/Enums`. Dilarang `enum()` di database.
- **Nilai enum Bahasa Inggris** (`in_transit`, `cancelled`); **label UI Bahasa Indonesia** lewat method `label()`. Daftar nilai & label ada di `PRD.md` §9.
- Cast di Model: `'status' => StatusPurchaseOrder::class`.
- **Transisi status hanya di Service**, lewat method yang memeriksa status asal (mis. `confirm()`, `cancel()`). Transisi yang tidak sah dilempar sebagai exception dengan pesan Indonesia. Dilarang mengubah `status` langsung dari Controller atau lewat `update($request->all())`.
- Status PO dan status sengketa **terpisah** (PRD BR-14); jangan menambah status sengketa ke enum PO.

```php
enum StatusPurchaseOrder: string
{
    case Draft = 'draft';
    case Negotiation = 'negotiation';
    case Agreed = 'agreed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Negotiation => 'Negosiasi',
            self::Agreed => 'Disepakati',
        };
    }
}
```

### 9.3 Multi-Tenant (WAJIB)

Setiap data milik perusahaan pembeli (Tenant) atau Vendor **harus terisolasi** (PRD NFR-02).

- Setiap query di Repository untuk data milik Tenant/Vendor **wajib** dibatasi ke Tenant/Vendor milik user yang sedang login (mis. `where('tenant_id', $actor->tenant_id)` atau scope `forTenant()` / `forVendor()`).
- Method Repository untuk data ber-tenant menerima `User $actor` (atau id Tenant/Vendor) secara eksplisit; jangan mengambil `auth()` di dalam Repository.
- Data milik Tenant lain dianggap **tidak ada (404)**.
- Policy tetap memeriksa kepemilikan sebagai lapis kedua (bagian 4.5).
- Superadmin hanya mengakses lintas Tenant untuk fitur platform (verifikasi, master global, blokir global), lewat method Repository terpisah yang jelas namanya (mis. `paginateAllTenants()`).
- Tenant & Vendor yang belum diverifikasi atau diblokir global tidak boleh login (PRD BR-19, BR-21).

### 9.4 Data Immutable

Data berikut **tidak boleh di-UPDATE maupun DELETE** setelah dibuat (PRD BR-17): pesan negosiasi, foto bukti inspeksi, log sengketa, sengketa `resolved`, credit note & retur `confirmed`, harga PO yang terkunci.

- Model immutable hanya punya `created_at` (`const UPDATED_AT = null;`) dan Repository-nya **hanya** menyediakan method `create` + baca.
- Tidak ada route `update`/`destroy` untuk data immutable.
- Koreksi dilakukan dengan menambah record baru (mis. entri log baru), bukan mengubah record lama.

---

## 10. Autentikasi

- Login email memakai **Fortify**; login Google memakai **Socialite**.
- Login (email maupun Google) **tidak boleh membuat user baru** bila akun belum terdaftar. Tampilkan error pada input email / tombol Google.
- Jalur pembuatan user hanya yang ada di PRD: registrasi Tenant (Owner), registrasi Vendor (mandiri/undangan), penerimaan undangan staf, dan pembuatan akun pegawai oleh Owner. Jangan membuat jalur lain.
- Login ditolak bila user nonaktif, Tenant/Vendor belum `approved`, atau vendor diblokir global.
- Setelah login: `session()->regenerate()`. Saat logout: `invalidate()` + `regenerateToken()`.
- Password selalu di-hash. Jangan pernah log password, token, atau secret.
- Remember me dikirim sebagai `remember` (boolean); masa berlaku maksimal 30 hari. Sesi idle berakhir 30 menit (PRD NFR-03).
- Token reset kata sandi berlaku 60 menit. Rate limit login lewat Fortify.

---

## 11. Standar Kode

### 11.1 Penamaan & Bahasa

| Hal | Bahasa / Format | Contoh |
|---|---|---|
| Tabel, kolom, nilai enum, key Resource | Inggris, snake_case | `purchase_orders`, `unit_price`, `in_transit` |
| Nama class/file domain (Model, Controller, Service, Repository, Policy, Request, Page) | Class yang sudah ada **tetap**; class untuk entitas baru **Bahasa Inggris** | Lama: `Komoditas`, `KomoditasService`, `Pegawai`, `Sengketa`. Baru: `CreditNote`, `PaymentService`, `PurchaseReturnPolicy` |
| Method, variabel, properti, komentar | Inggris, camelCase | `findByUuid()`, `$purchaseOrder` |
| Teks untuk user (UI, validasi, flash, email, label enum) | Indonesia | `'Data tidak ditemukan.'` |
| Route name | dot.notation, mengikuti prefix domain | `komoditas.index`, `purchase-orders.show` |

- Bila nama class berbeda dari nama tabel, Model **wajib** mendeklarasikan `protected $table` (contoh: `Komoditas` → `commodities`).
- Jangan mengganti nama class domain yang sudah ada tanpa diminta.

### 11.2 PHP
- **PSR-12**; jalankan `vendor/bin/pint --dirty --format agent` sebelum selesai.
- `declare(strict_types=1);` pada file baru.
- Type hint semua parameter & return type. `readonly` untuk properti constructor promotion.
- Class `PascalCase`, method/variabel `camelCase`, konstanta `UPPER_SNAKE_CASE`.
- PHPDoc singkat bila maksud tidak jelas dari signature.
- Early return dibanding nested `if`.
- Tidak ada logika bisnis/cek izin di Controller, tidak ada query Eloquent di luar Repository, tidak ada status code hardcode di luar `ResponseHelper`.

### 11.3 React / JavaScript
- Komponen `PascalCase`; file mengikuti bagian 3.2.
- Pisahkan komponen reusable ke `Components/` bila dipakai lebih dari satu halaman; komponen khusus modul ke `Pages/<Modul>/components/`.
- Format angka/uang/tanggal memakai helper `@/lib/format` (Rupiah, WIB).

### 11.4 Umum
- Komentar hanya untuk menjelaskan **kenapa**, bukan mengulang **apa**.
- Satu fungsi satu tanggung jawab. DRY tanpa abstraksi prematur.
- Jangan ubah format/gaya kode di luar bagian yang dikerjakan.

---

## 12. Testing

- Setiap fitur baru atau perubahan perilaku **wajib** disertai Feature test di `tests/Feature/`.
- Gaya test mengikuti `tests/Feature/KomoditasTest.php`: **class PHPUnit** (`extends TestCase`, `use RefreshDatabase`), seed `RoleSeeder` di `setUp()`, data dibuat lewat **factory**. Buat dengan `php artisan make:test --phpunit NamaTest` (bukan Pest).
- Minimal yang diuji per fitur:
  - Alur sukses (data tersimpan, redirect/flash/JSON benar).
  - Validasi gagal (422 / `assertSessionHasErrors`).
  - **Otorisasi**: role yang tidak berhak ditolak (403) — setiap ability Policy punya test.
  - **Isolasi Tenant**: user Tenant A tidak bisa melihat/mengubah data Tenant B (404).
  - Transisi status yang tidak sah ditolak.
- Jalankan test yang relevan: `php artisan test --compact --filter=NamaTest`; sebelum selesai jalankan seluruh suite `php artisan test --compact`.

---

## 13. Keamanan

- Selalu validasi lewat Form Request; jangan percaya input user.
- Mass assignment protection; jangan `Model::create($request->all())`.
- Jangan bocorkan detail internal (exception, SQL, path) ke response.
- **Otorisasi lewat Policy** pada setiap aksi (bagian 4.5) **dan** scoping Tenant di Repository (bagian 9.3).
- Secret hanya di `.env`, diakses lewat `config()`. Callback payment gateway wajib diverifikasi signature-nya.
- CSRF bawaan Inertia/Laravel jangan dinonaktifkan (kecuali endpoint callback payment gateway yang diverifikasi signature).
- Cegah IDOR: jangan ekspos `id`; pakai `uuid`/`slug`.
- Kirim data lewat API Resource dengan field eksplisit; data sensitif per pihak (Harga Pagu, estimasi harga) hanya untuk pihak yang berhak.

---

## 14. Checklist Sebelum Selesai

- [ ] Fitur sesuai `PRD.md` (use case, FR, BR); tidak ada fitur/status/role karangan.
- [ ] Struktur folder & nama file mengikuti bagian 3 (perhatikan `Pages/`, `Components/`, `.jsx`).
- [ ] Controller tipis; tidak ada query, logika bisnis, atau cek role di dalamnya.
- [ ] Setiap aksi diotorisasi lewat **Policy** (Form Request `authorize()` atau `Gate::forUser()->authorize()` di Service); tidak ada `before()` yang meloloskan Superadmin.
- [ ] Query data ber-tenant dibatasi Tenant/Vendor di Repository; data Tenant lain → 404.
- [ ] Service memakai Repository **interface**; binding terdaftar di `RepositoryServiceProvider`.
- [ ] Tabel/kolom/enum berbahasa Inggris; Model mendeklarasikan `$table` bila perlu; status memakai Backed Enum + `label()`.
- [ ] Transisi status hanya lewat method Service yang memeriksa status asal.
- [ ] Data immutable tidak punya jalur update/delete.
- [ ] Validasi memakai Form Request (backend) + validasi frontend; pesan Bahasa Indonesia.
- [ ] Semua controller memakai `ResponseHelper`; `AuthorizationException` → `forbidden()`.
- [ ] Tidak ada `Inertia::render` / closure logika di file route.
- [ ] Route resource memakai `{uuid}` / `{slug}` dengan constraint.
- [ ] Tidak ada `id` di props, JSON, URL, atau form; data lewat API Resource; izin lewat flag `can.*`.
- [ ] Error validasi tampil sebagai state invalid; tidak ada atribut `required`; `<form noValidate>`.
- [ ] Halaman daftar memakai Data Table server-side, sort di-whitelist, `getRowId` = `uuid`.
- [ ] Select banyak opsi memakai Combobox; value = `uuid`.
- [ ] Input file memakai `FileDropzone`; batas sama dengan Form Request; nama file di-generate server.
- [ ] Notifikasi lewat `notify` (satu suara per event); flash lewat `flash-handler`.
- [ ] Bentuk form sesuai bagian 8.9; semua button async memakai `LoadingButton`.
- [ ] Feature test ditulis (sukses, validasi, otorisasi, isolasi Tenant) dan `php artisan test` lulus.
- [ ] `vendor/bin/pint --dirty --format agent`, `php artisan route:list`, dan `npm run build` berjalan tanpa error.

---

## 15. Format Jawaban Agent

1. **Asumsi** (jika ada) dan rujukan PRD yang dipakai (UC/FR/BR)
2. **Daftar file:** dibuat / diubah / dihapus
3. **Kode** (atau langsung diterapkan ke project)
4. **Perintah** yang perlu dijalankan (`php artisan migrate`, `npm run build`, dll.)
5. **Hasil verifikasi** sesuai checklist bagian 14 (termasuk output test)
