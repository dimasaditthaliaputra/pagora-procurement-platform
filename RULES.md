# RULES.md — Standar Penulisan Kode Project

> Dokumen ini adalah aturan tetap untuk semua AI agent yang mengerjakan project ini.
> Baca seluruh dokumen ini sebelum menulis kode. Pemilik project hanya akan menjelaskan **flow/fitur**; semua keputusan teknis (struktur, pola, penamaan, validasi, response) mengikuti dokumen ini tanpa perlu ditanyakan ulang.

---

## 1. Stack & Konteks

- **Backend:** Laravel (PHP), Laravel Fortify (auth email), Laravel Socialite (Google OAuth)
- **Frontend:** Inertia.js + React + Vite, **satu project** (monolith, bukan repo terpisah)
- **UI:** shadcn/ui (sudah terinstall) + Tailwind CSS
- **Komponen data & input:** shadcn Data Table + `@tanstack/react-table`, shadcn Combobox (Popover + Command), `react-dropzone` (aturan di bagian 8.5–8.10)
- **Aset suara:** `public/assets/sounds/success.mp3` dan `public/assets/sounds/failure.mp3` (sudah disediakan, jangan dibuat/diganti)
- **Arsitektur backend:** MVC + Service Repository Pattern
- **Bahasa:** pesan error/validasi untuk user memakai **Bahasa Indonesia**; nama class, method, variabel, dan komentar kode memakai **Bahasa Inggris**.

---

## 2. Cara Kerja Agent (Wajib Diikuti)

Setiap menerima tugas:

1. **Pahami flow** yang dijelaskan user. Jika ada hal ambigu yang memengaruhi hasil, tulis asumsi di awal jawaban lalu lanjutkan. Jangan bertanya hal yang sudah dijawab oleh dokumen ini.
2. **Periksa kode yang sudah ada** (model, migration, route, komponen terkait) sebelum membuat file baru. Gunakan ulang yang sudah ada; jangan membuat duplikat.
3. **Kerjakan berurutan:** Migration/Model → Repository → Service → Form Request → Controller → Route → Frontend.
4. **Jangan mengubah file yang tidak berkaitan** dengan tugas.
5. **Jangan install package baru** tanpa persetujuan user. Gunakan yang sudah terinstall. Pengecualian: `@tanstack/react-table`, `react-dropzone`, dan komponen shadcn pendukung (lihat bagian 8.5–8.8) sudah disetujui; cek `package.json` dulu, lalu install hanya yang belum ada.
6. Setelah selesai, berikan: daftar asumsi, file dibuat/diubah/dihapus, perintah yang perlu dijalankan, dan hasil verifikasi.

---

## 3. Struktur Folder

### 3.1 Backend

```
app/
├── Helpers/
│   └── ResponseHelper.php
├── Http/
│   ├── Controllers/            # Controller tipis, 1 controller per domain/fitur
│   ├── Middleware/
│   ├── Resources/              # API Resource (field eksplisit, tanpa id)
│   └── Requests/               # Form Request, dikelompokkan per domain
│       └── Auth/
│           ├── LoginRequest.php
│           └── RegisterRequest.php
├── Models/
├── Providers/
│   └── RepositoryServiceProvider.php
├── Repositories/
│   ├── Contracts/              # Interface
│   │   └── UserRepositoryInterface.php
│   └── Eloquent/               # Implementasi
│       └── UserRepository.php
└── Services/                   # Logika bisnis
    ├── AuthService.php
    └── FileUploadService.php   # simpan/hapus file (Storage), dipakai Service lain
```

Aturan penamaan file:
- Controller: `{Domain}Controller` (contoh: `AuthController`, `ProductController`)
- Service: `{Domain}Service`
- Repository interface: `{Model}RepositoryInterface`; implementasi: `{Model}Repository`
- Form Request: `{Action}{Model}Request` atau `{Action}Request` di dalam folder domain (contoh: `Product/StoreProductRequest`)

### 3.2 Frontend

Ikuti struktur yang sudah ada di `resources/js`. Jika belum ada, gunakan:

```
resources/js/
├── pages/                # Halaman Inertia (dirender dari controller)
│   └── auth/
│       ├── login.(tsx|jsx)
│       └── register.(tsx|jsx)
├── components/
│   ├── ui/               # Komponen shadcn (jangan diedit tanpa alasan kuat)
│   ├── data-table/       # DataTable generik (TanStack Table)
│   ├── combobox.tsx      # Combobox reusable (Popover + Command)
│   ├── file-dropzone.tsx # Dropzone reusable (react-dropzone)
│   ├── loading-button.tsx # Button dengan state loading (bagian 8.10)
│   ├── flash-handler.tsx # toast + sound dari flash message
│   └── ...               # Komponen reusable buatan sendiri
├── layouts/
├── hooks/
├── lib/                  # utils: cn(), sound.ts (playSound), notify.ts (toast + sound)
└── types/
```

Deteksi dulu apakah project memakai **TypeScript atau JavaScript**, lalu konsisten dengannya.

---

## 4. Arsitektur Backend: MVC + Service Repository

### 4.1 Alur & Tanggung Jawab

```
Route → Controller → Service → Repository (Interface) → Model
```

| Layer | Boleh | Dilarang |
|---|---|---|
| **Route** | Memetakan URL ke method controller, memasang middleware | `return view()`, `Inertia::render()`, closure berisi logika |
| **Controller** | Terima request, panggil Service, kembalikan response lewat `ResponseHelper` / `Inertia::render` | Query Eloquent, logika bisnis, validasi inline |
| **Form Request** | Aturan validasi + pesan error Bahasa Indonesia, otorisasi | Logika bisnis |
| **Service** | Logika bisnis, orkestrasi beberapa repository, transaksi DB, melempar exception | Akses `Request`, return response HTTP, query Eloquent langsung |
| **Repository** | Akses data (query Eloquent) | Logika bisnis, return response HTTP |
| **Model** | Relasi, casts, fillable, scope, accessor | Logika bisnis kompleks |

### 4.2 Aturan Dependency

- Controller meng-inject **Service** lewat constructor.
- Service meng-inject **Repository Interface** (bukan class konkret) lewat constructor.
- Binding interface → implementasi dilakukan di `RepositoryServiceProvider` dan didaftarkan di `bootstrap/providers.php`.
- Satu Repository untuk satu Model. Satu Service untuk satu domain; boleh memanggil Service lain bila perlu.

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
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(): Response
    {
        return Inertia::render('auth/login');
    }

    public function storeAuth(LoginRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->authService->login(
                $request->only('email', 'password'),
                $request->boolean('remember')
            );

            return ResponseHelper::success('Login berhasil.', redirectTo: route('dashboard'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::validationError($e->errors());
        } catch (Throwable $e) {
            return ResponseHelper::serverError($e);
        }
    }
}
```

### 4.4 Controller Global per Domain

- Satu controller per domain/fitur. Untuk autentikasi: **satu `AuthController`** untuk semua tipe auth (email & Google) dengan method seperti `login`, `storeAuth`, `register`, `storeRegister`, `redirectToGoogle`, `handleGoogleCallback`, `logout`.
- Method yang me-render halaman dan method yang memproses aksi dipisah (`login` vs `storeAuth`).
- Controller yang sudah tidak terpakai (misalnya controller Google lama) **harus dihapus** beserta route-nya.

---

## 5. Routes

- Semua route **wajib** menunjuk ke method controller:
  ```php
  Route::get('/login', [AuthController::class, 'login'])->name('login');
  ```
- **Dilarang** di `routes/*.php`: `return view(...)`, `Inertia::render(...)`, `Route::inertia(...)`, atau closure yang berisi logika.
- Beri `name()` pada setiap route. Kelompokkan dengan `Route::middleware(...)->group(...)` dan `Route::controller(...)->group(...)`.
- Middleware: `guest` untuk login/register/Google, `auth` untuk logout dan halaman terproteksi.
- Jika route bawaan Fortify bentrok dengan route controller sendiri, gunakan `Fortify::ignoreRoutes()` dan `'views' => false` di `config/fortify.php`; tetap manfaatkan action, rate limiter, dan pipeline Fortify.
- **Parameter route untuk resource (show/edit/update/destroy, dll.) WAJIB memakai `uuid` atau `slug`, BUKAN `id` auto-increment.** Lihat bagian 5.1.

### 5.1 Identifier Publik: UUID / Slug (WAJIB)

Tujuan: mencegah enumerasi data dan IDOR (user menebak/mengubah `id` di URL, form, atau request), serta tidak membocorkan jumlah/urutan data.

**Prinsip**
- Primary key `id` (auto-increment) **hanya dipakai internal backend**: relasi/foreign key, join, dan query antar tabel.
- `id` **tidak boleh keluar ke frontend** dalam bentuk apa pun: URL/route param, props Inertia, response JSON, field form (termasuk hidden input), atribut HTML (`data-id`, `key` yang bersumber dari id), maupun query string.
- Identifier yang dipakai di luar backend:
  - **`uuid`** → default untuk semua entitas CRUD.
  - **`slug`** → untuk konten yang butuh URL mudah dibaca (artikel, produk publik, dsb.). Slug harus `unique`, dan boleh dipakai bersama `uuid`.
- **UUID/slug bukan pengganti otorisasi.** Pemeriksaan kepemilikan/hak akses (Policy/Gate) tetap wajib di setiap aksi. UUID hanya mempersulit penebakan, bukan membuat akses otomatis aman.

**Migration**
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();                               // internal only
    $table->uuid('uuid')->unique();             // identifier publik
    $table->string('slug')->unique();           // hanya jika dibutuhkan
    $table->string('name');
    $table->timestamps();
});
```
Untuk tabel yang sudah ada: buat migration baru (tambah kolom `uuid` nullable → isi data lama → ubah jadi `unique` + not null). Jangan edit migration lama.

**Model**
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Product extends Model
{
    use HasUuids;

    protected $hidden = ['id'];                 // id tidak ikut ter-serialize

    public function uniqueIds(): array
    {
        return ['uuid'];                        // generate otomatis ke kolom uuid
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';                          // ganti 'slug' bila memakai slug
    }
}
```
Foreign key seperti `category_id` juga harus disembunyikan (`$hidden`) atau tidak diikutkan saat data dikirim ke frontend.

**Route** — gunakan constraint format agar input asal ditolak sebelum masuk controller:
```php
Route::controller(ProductController::class)
    ->prefix('products')
    ->name('products.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{uuid}', 'show')->whereUuid('uuid')->name('show');
        Route::put('/{uuid}', 'update')->whereUuid('uuid')->name('update');
        Route::delete('/{uuid}', 'destroy')->whereUuid('uuid')->name('destroy');
    });

// Jika memakai slug:
// Route::get('/{slug}', 'show')->where('slug', '[a-z0-9\-]+')->name('show');
```
- Hindari nama param yang menyiratkan `id` (`{id}`, `{product_id}`).
- Controller menerima `string $uuid` lalu meneruskannya ke Service. **Tidak memakai implicit route model binding** yang melewati Repository, supaya pola Controller → Service → Repository tetap konsisten.

**Repository & Service**
```php
// Repository
public function findByUuid(string $uuid): ?Product
{
    return $this->model->where('uuid', $uuid)->first();
}

// Service
public function findByUuid(string $uuid): Product
{
    return $this->products->findByUuid($uuid)
        ?? throw new ModelNotFoundException('Data tidak ditemukan.');
}
```
- `ModelNotFoundException` ditangkap di Controller dan dikembalikan lewat `ResponseHelper::notFound()` (404).
- Untuk data milik user lain, kembalikan respons yang konsisten (404 atau 403) tanpa membocorkan apakah data tersebut ada.

**Controller**
```php
public function show(string $uuid): Response|JsonResponse|RedirectResponse
{
    try {
        $product = $this->productService->findByUuid($uuid);

        return Inertia::render('products/show', [
            'product' => new ProductResource($product),
        ]);
    } catch (ModelNotFoundException) {
        return ResponseHelper::notFound('Data tidak ditemukan.');
    } catch (Throwable $e) {
        return ResponseHelper::serverError($e);
    }
}
```

**Data ke Frontend** — jangan kirim model mentah ke Inertia. Gunakan **API Resource** (atau DTO) dengan field eksplisit:
```php
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'slug'          => $this->slug,
            'name'          => $this->name,
            'category_uuid' => $this->category?->uuid, // relasi pun lewat uuid, bukan id
        ];
    }
}
```

**Input dari Frontend (relasi/foreign key)**
- Frontend mengirim `category_uuid`, bukan `category_id`.
- Validasi di Form Request: `'category_uuid' => ['required', 'uuid', 'exists:categories,uuid']`.
- **Service** yang menerjemahkan uuid → id internal lewat Repository sebelum menyimpan data.
- Aksi massal (bulk delete, dll.) mengirim array `uuid`, divalidasi `'uuids.*' => ['uuid']`.

**Frontend**
```tsx
// Link & aksi memakai uuid / slug
<Link href={route('products.show', product.uuid)}>{product.name}</Link>
router.delete(route('products.destroy', product.uuid));

// key list memakai uuid
{products.map((p) => <Row key={p.uuid} product={p} />)}
```
- Tipe data frontend memakai `uuid: string` (dan `slug: string` bila ada); **tidak ada field `id`**.

---

## 6. Response Helper

Semua controller **wajib** memakai `App\Helpers\ResponseHelper`. Dilarang menulis `response()->json(..., 200)` atau `abort(500)` langsung di controller.

### 6.1 Method Minimal

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

### 6.2 Format JSON (konsisten)

```json
{
  "success": true,
  "message": "Pesan untuk user",
  "data": null,
  "errors": null
}
```

### 6.3 Perilaku Inertia

- Jika `request()->expectsJson()` → kembalikan JSON dengan status code yang sesuai.
- Jika bukan (request Inertia biasa):
  - Sukses → `redirect()->intended($redirectTo)` atau `back()` dengan `->with('success', $message)`.
  - Gagal validasi → `back()->withErrors($errors)` agar error masuk ke props `errors` Inertia (tanpa flash `error`).
  - Gagal non-validasi (400/403/404/500) → `back()->with('error', $message)`.
  - Flash `success` / `error` dibagikan ke frontend lewat `HandleInertiaRequests::share()` (`'flash' => ['success' => fn () => $request->session()->get('success'), 'error' => fn () => $request->session()->get('error')]`) dan ditangani satu tempat oleh `flash-handler` (bagian 8.8).
- `serverError()` wajib mencatat `Log::error(...)` dan menampilkan pesan generik ke user (jangan membocorkan detail exception, stack trace, atau query).

---

## 7. Validasi

- **Backend:** wajib memakai **Form Request** (`php artisan make:request`). Jangan validasi inline di controller.
- **Frontend:** validasi sebelum submit (required, format email, panjang minimum, konfirmasi password, dsb.) agar user mendapat feedback cepat.
- Frontend **tidak menggantikan** backend: backend selalu memvalidasi ulang.
- Pesan error memakai Bahasa Indonesia, didefinisikan di method `messages()` pada Form Request.
- Aturan unik/exists wajib di backend (contoh: `unique:users,email`).

---

## 8. Aturan Frontend

### 8.1 Umum

- Halaman = file di `pages/`, dirender dari controller via `Inertia::render('folder/nama')`.
- Gunakan **functional component + hooks**. Dilarang class component.
- Gunakan komponen **shadcn/ui yang sudah terinstall** (`Button`, `Input`, `Label`, `Checkbox`, `Card`, dll.). Jangan membuat ulang komponen yang sudah ada di shadcn. Jika butuh komponen shadcn yang belum ada, **tanyakan dulu** sebelum menambahkan — kecuali komponen yang dibutuhkan bagian 8.5–8.10 (`table`, `popover`, `command`, `dialog`, `alert-dialog`, `dropdown-menu`, `sonner`/toast), yang boleh langsung ditambahkan lewat CLI shadcn. Package `@tanstack/react-table` dan `react-dropzone` juga sudah disetujui; install hanya jika belum ada di `package.json`.
- Gunakan Tailwind; hindari inline style. Gunakan helper `cn()` untuk class kondisional.
- Tidak ada `console.log`, kode mati, atau import tak terpakai yang tertinggal.
- Identifier data di frontend **hanya `uuid` / `slug`**. Dilarang memakai, menerima, atau mengirim `id` auto-increment (URL, props, form, hidden input, `key`).

### 8.2 Form (Inertia `useForm`)

- Selalu gunakan `useForm` dari `@inertiajs/react`.
- **Nilai awal form harus kosong** (`''`, `false`, `[]`). Dilarang `defaultValue`, email/password dummy, atau placeholder berisi kredensial contoh.
- Disable tombol submit saat `processing`.
- Panggil `clearErrors('field')` saat user mengubah input terkait.
- Elemen `<form>` wajib memakai `noValidate`. **Dilarang memakai atribut HTML `required`** (dan `pattern`, `minLength`, `maxLength`, dll. untuk validasi) pada input; validasi dilakukan lewat JavaScript + backend, dan hasilnya ditampilkan dengan **class invalid** (bagian 8.3). Penanda wajib diisi cukup berupa tanda `*` pada label.
- Setiap button yang memicu proses async wajib punya state loading (bagian 8.10).

### 8.3 Tampilan Error (PENTING)

- Error validasi ditampilkan sebagai **state invalid pada input**, bukan alert/toast/`window.alert`.
- Setiap input yang error wajib:
  - `aria-invalid={!!errors.field}`
  - class invalid, contoh: `border-destructive focus-visible:ring-destructive`
  - pesan error kecil di bawah input (`<p className="text-sm text-destructive">`)
- Berlaku juga untuk elemen non-input (contoh: tombol Google dengan `errors.google` diberi state invalid + pesan di bawahnya).
- Error **validasi** tetap hanya tampil sebagai state invalid pada input (tanpa toast/alert), tetapi **sound failure tetap diputar** (lihat bagian 8.8).
- Toast dipakai untuk notifikasi **sukses/info** dan error **non-validasi** (server error, 403/404, gagal upload, dsb.), selalu lewat `notify` (bagian 8.8), bukan memanggil `toast.*` langsung.

### 8.4 Contoh Pola Input

```tsx
<div className="space-y-2">
  <Label htmlFor="email">Email</Label>
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

### 8.5 Data Table (shadcn Data Table + TanStack Table)

Semua halaman daftar data (index CRUD) **wajib** memakai Data Table, bukan `<table>` manual.

**Aturan**
- Komponen generik `components/data-table/` (DataTable, toolbar, pagination, column header) dipakai ulang di semua modul. Jangan menyalin ulang per modul.
- Definisi kolom di file terpisah per modul: `pages/<modul>/columns.tsx`.
- Gunakan **server-side** pagination, sorting, dan filtering (`manualPagination`, `manualSorting`, `manualFiltering`). Dilarang memuat seluruh data lalu memfilter di browser.
- `getRowId: (row) => row.uuid` (bukan id auto-increment).
- Search memakai debounce (±400 ms); state tabel (page, search, sort, per_page) disimpan di query string lewat Inertia `router.get` dengan `preserveState`, `preserveScroll`, `replace`, dan `only`.
- Tampilkan state loading (skeleton/overlay) selama request berjalan, dan empty state ("Belum ada data") saat kosong.
- Kolom aksi memakai `DropdownMenu` (Detail / Edit / Hapus) atau tombol ikon; semua aksi memakai `uuid`/`slug`. Hapus selalu lewat `AlertDialog` konfirmasi dengan tombol loading (bagian 8.10).

**Frontend**
```tsx
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
  route('products.index'),
  { search, sort, direction, page, per_page },
  {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['products', 'filters'],
    onStart: () => setLoading(true),
    onFinish: () => setLoading(false),
  },
);
```

**Backend** — Repository melakukan paginate dengan whitelist kolom sort; Service meneruskan filter; Controller memakai Form Request untuk query string:
```php
// Repository
private const SORTABLE = ['name', 'created_at'];

public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
{
    $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'created_at';
    $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

    return $this->model
        ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
        ->orderBy($sort, $direction)
        ->paginate(min($perPage, 100))
        ->withQueryString();
}

// Controller
public function index(IndexProductRequest $request): Response
{
    $products = $this->productService->paginate($request->validated());

    return Inertia::render('products/index', [
        'products' => ProductResource::collection($products), // data + meta + links
        'filters'  => $request->only('search', 'sort', 'direction', 'per_page'),
    ]);
}
```
- Kolom sort **wajib whitelist**; jangan memasukkan input user langsung ke `orderBy`.
- `per_page` dibatasi (maks 100). Data dikirim lewat API Resource (tanpa `id`).

### 8.6 Combobox (shadcn Combobox: Popover + Command)

Dipakai untuk select yang punya banyak opsi atau perlu pencarian (relasi kategori, user, dsb.). Untuk 2–5 opsi statis, cukup shadcn `Select`.

**Aturan**
- Buat satu komponen reusable `components/combobox.tsx`; jangan menulis ulang Popover + Command di tiap form.
- **Value yang disimpan/dikirim adalah `uuid`** (atau slug), bukan id. Opsi berbentuk `{ value: uuid, label: string }`.
- Props minimal: `options`, `value`, `onChange`, `placeholder`, `error`, `disabled`, `loading`, `clearable`.
- State invalid: trigger diberi `aria-invalid` + `border-destructive`, pesan error di bawahnya (sama seperti input biasa).
- Opsi sedikit/menengah (≤ ±100) → kirim lewat props Inertia. Opsi banyak → **async search**: request ke endpoint controller (JSON via `ResponseHelper`), dengan debounce, batas hasil (maks 20–50), dan tampilkan loading di dalam list.
- Trigger berupa `Button type="button"` agar tidak men-submit form.

```tsx
<Combobox
  options={categories}                 // [{ value: uuid, label: 'Elektronik' }]
  value={data.category_uuid}
  onChange={(v) => {
    setData('category_uuid', v);
    clearErrors('category_uuid');
  }}
  placeholder="Pilih kategori"
  error={errors.category_uuid}
/>
```
```tsx
// di dalam komponen Combobox
<PopoverTrigger asChild>
  <Button
    type="button"
    variant="outline"
    role="combobox"
    aria-expanded={open}
    aria-invalid={!!error}
    className={cn('w-full justify-between', error && 'border-destructive')}
  >
    {selected?.label ?? placeholder}
    <ChevronsUpDown className="opacity-50" />
  </Button>
</PopoverTrigger>
<PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0">
  <Command>
    <CommandInput placeholder="Cari..." />
    <CommandList>
      <CommandEmpty>Tidak ditemukan.</CommandEmpty>
      <CommandGroup>
        {options.map((o) => (
          <CommandItem key={o.value} value={o.label} onSelect={() => { onChange(o.value); setOpen(false); }}>
            {o.label}
          </CommandItem>
        ))}
      </CommandGroup>
    </CommandList>
  </Command>
</PopoverContent>
```
- Backend memvalidasi hasilnya: `'category_uuid' => ['required', 'uuid', 'exists:categories,uuid']`.

### 8.7 File Upload (react-dropzone)

Semua input file/gambar memakai komponen reusable `components/file-dropzone.tsx` berbasis `react-dropzone`. Dilarang `<input type="file">` polos.

**Aturan frontend**
- `accept`, `maxSize`, dan `maxFiles` di dropzone **harus sama** dengan aturan Form Request.
- File yang ditolak (tipe/ukuran/jumlah) ditampilkan sebagai **state invalid** pada area dropzone (`aria-invalid`, `border-destructive`) + pesan error di bawahnya, lewat `setError`.
- Tampilkan preview (gambar) atau nama + ukuran file, dan tombol hapus. Preview dari `URL.createObjectURL` **wajib** di-`revokeObjectURL` saat unmount/ganti file.
- Saat upload: dropzone `disabled`, tombol submit loading, dan tampilkan progress dari `progress` milik `useForm` bila file besar.
- Inertia otomatis mengirim `FormData` ketika ada `File`. Untuk **update dengan file**, kirim lewat `post` dengan `_method: 'put'` (PHP tidak mem-parsing multipart PUT).
- Saat edit, tampilkan file lama (URL dari Resource). Jika user menghapusnya, kirim flag `remove_<field>: true`.

```tsx
const { getRootProps, getInputProps, isDragActive } = useDropzone({
  accept: { 'image/*': ['.jpg', '.jpeg', '.png', '.webp'] },
  maxSize: 2 * 1024 * 1024,
  maxFiles: 1,
  multiple: false,
  disabled: processing,
  onDropAccepted: (files) => {
    setData('image', files[0]);
    clearErrors('image');
  },
  onDropRejected: () => {
    setError('image', 'File harus berupa gambar JPG/PNG/WEBP maksimal 2 MB.');
    notify.validationFailed();
  },
});
```

**Aturan backend**
- Validasi di Form Request: `'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']` (pesan Bahasa Indonesia).
- Penyimpanan dilakukan di **Service** (`Storage::disk('public')`), dengan nama file yang **di-generate server** (uuid/hash). Jangan memakai nama file dari client.
- Simpan path di database; kirim URL ke frontend lewat Resource (`Storage::url(...)`).
- Hapus file lama saat diganti atau saat record dihapus. Jangan percaya ekstensi/MIME dari client; validasi di server.
- Pastikan symlink `php artisan storage:link` sudah ada.

### 8.8 Sound & Notifikasi (success / failure)

Setiap pesan **success** dan **error** wajib disertai suara:
- `public/assets/sounds/success.mp3` → pesan sukses
- `public/assets/sounds/failure.mp3` → pesan error

File suara sudah disediakan. Jika file tidak ditemukan, laporkan ke user; jangan membuat atau mengganti dengan file lain.

**Aturan**
- Dilarang memanggil `new Audio(...)` atau `toast.*` langsung di komponen. Semua lewat `lib/sound.ts` dan `lib/notify.ts`.
- **Satu event = satu suara.** Jangan memutar suara per field error; jangan memutar dua kali untuk satu kejadian.
- Kapan suara diputar:
  - Sukses (flash `success`, atau `onSuccess` form) → `notify.success(message)` (suara success + toast).
  - Error non-validasi (flash `error`, 403/404/500, gagal upload/jaringan) → `notify.error(message)` (suara failure + toast).
  - Error validasi (backend `onError` atau validasi frontend) → `notify.validationFailed()` (**hanya suara failure**; pesan tetap inline pada input dengan class invalid, tanpa toast).
- Flash message dari backend ditangani **satu tempat**: `components/flash-handler.tsx` yang dipasang di layout utama. Komponen lain tidak boleh membaca flash sendiri.
- Browser membatasi autoplay; suara diputar dari respons terhadap aksi user (submit/klik), jadi aman. Tetap tangkap error `play()` agar tidak muncul error di console.
- Guard SSR: `playSound` tidak boleh dijalankan jika `window` tidak ada.

```ts
// lib/sound.ts
export type SoundType = 'success' | 'failure';

const SOURCES: Record<SoundType, string> = {
  success: '/assets/sounds/success.mp3',
  failure: '/assets/sounds/failure.mp3',
};

const cache: Partial<Record<SoundType, HTMLAudioElement>> = {};

export function playSound(type: SoundType): void {
  if (typeof window === 'undefined') return;

  const audio = (cache[type] ??= new Audio(SOURCES[type]));
  audio.currentTime = 0;
  audio.play().catch(() => {});
}
```
```ts
// lib/notify.ts
import { toast } from 'sonner';
import { playSound } from '@/lib/sound';

export const notify = {
  success(message: string) {
    playSound('success');
    toast.success(message);
  },
  error(message: string) {
    playSound('failure');
    toast.error(message);
  },
  validationFailed() {
    playSound('failure'); // pesan tampil inline pada input
  },
};
```
```tsx
// components/flash-handler.tsx (dipasang di layout utama)
export function FlashHandler() {
  const { flash } = usePage<SharedProps>().props;

  useEffect(() => {
    if (flash?.success) notify.success(flash.success);
  }, [flash?.success]);

  useEffect(() => {
    if (flash?.error) notify.error(flash.error);
  }, [flash?.error]);

  return null;
}
```
```tsx
// pola di form
post(route('products.store'), {
  onError: () => notify.validationFailed(), // sukses ditangani flash-handler
});
```
- Jika proyek belum memakai toast (Sonner), tambahkan komponen `sonner` dari shadcn dan pasang `<Toaster />` sekali di layout.

### 8.9 Modal vs Form Page

Pilih bentuk form berdasarkan jumlah dan jenis input:

| Kondisi | Bentuk |
|---|---|
| **≤ 4 field sederhana** (teks pendek, select/combobox, switch/checkbox), tanpa upload file | **Modal** (shadcn `Dialog`) di halaman index |
| **≥ 5 field**, atau ada upload file, textarea panjang/rich text, repeater/dynamic row, multi-step, atau layout kompleks | **Form page** terpisah (`create` / `edit`) |

Jika ragu, pilih **form page**.

**Aturan modal**
- Komponen form berada di `pages/<modul>/components/` dan memakai `useForm` yang sama polanya dengan form page.
- Modal dipakai untuk create **dan** edit; edit membawa data dari baris tabel (berdasarkan `uuid`), form direset saat dibuka untuk create.
- Modal **tidak ditutup saat ada error**; tutup hanya saat sukses (`onSuccess`), lalu `reset()` dan `clearErrors()`.
- Saat `processing`, modal tidak bisa ditutup (cegah klik overlay/ESC/tombol batal) dan semua tombol memakai state loading.
- Saat modal ditutup (batal), selalu `reset()` + `clearErrors()`.
- Konfirmasi hapus memakai `AlertDialog`, bukan `window.confirm`.
- Route/controller untuk modal cukup `store` dan `update` (tanpa halaman `create`/`edit`).

**Aturan form page**
- Route/controller lengkap: `create` (render), `store`, `edit` (render, berdasarkan `uuid`), `update`.
- Tombol "Batal"/"Kembali" dan tombol simpan; tombol simpan memakai state loading.
- Gunakan `Card` + grid responsif untuk mengelompokkan field.

### 8.10 State Loading pada Button & Invalid vs Required

**Loading button (WAJIB)**
- **Setiap button yang memicu proses async** (submit, simpan, hapus, login, login Google, logout, export, konfirmasi dialog, dll.) wajib punya state loading: `disabled` + spinner + (opsional) teks berubah.
- Gunakan komponen reusable `components/loading-button.tsx`, bukan merangkai spinner manual di tiap tempat.
- Sumber state: `processing` dari `useForm`, state lokal untuk `router.*` (`onStart`/`onFinish`), atau state dari fetch.
- Saat loading, tombol lain di form/dialog yang sama juga di-`disable` untuk mencegah submit ganda.

```tsx
// components/loading-button.tsx
import { Loader2 } from 'lucide-react';

type LoadingButtonProps = React.ComponentProps<typeof Button> & {
  loading?: boolean;
  loadingText?: string;
};

export function LoadingButton({
  loading = false,
  loadingText,
  children,
  disabled,
  ...props
}: LoadingButtonProps) {
  return (
    <Button disabled={disabled || loading} aria-busy={loading} {...props}>
      {loading && <Loader2 className="animate-spin" />}
      {loading && loadingText ? loadingText : children}
    </Button>
  );
}

// pemakaian
<LoadingButton type="submit" loading={processing} loadingText="Menyimpan...">
  Simpan
</LoadingButton>
```

**Invalid class, bukan `required`**
- Jangan memakai atribut `required` pada input (lihat 8.2). Field wajib divalidasi lewat JavaScript dan backend; field kosong/salah diberi **class invalid** + `aria-invalid` + pesan di bawahnya.
- Berlaku untuk semua jenis input: `Input`, `Textarea`, `Select`, `Combobox`, `Checkbox`, dan `FileDropzone`.
- Validasi frontend dijalankan saat submit dan memanggil `setError(field, pesan)` untuk setiap field yang gagal; jika ada error, hentikan submit, fokuskan field error pertama, lalu panggil `notify.validationFailed()` **satu kali**.

---

## 9. Database

- Perubahan skema **selalu** lewat migration baru. Jangan mengedit migration lama yang sudah dijalankan.
- Nama tabel plural snake_case; foreign key `{model}_id`; gunakan `foreignId()->constrained()` dengan aksi `onDelete` yang jelas.
- Tambahkan index pada kolom yang sering di-query/unik.
- Gunakan `$fillable` (atau `$guarded` secara sadar) dan `casts()` pada Model.
- Operasi yang mengubah lebih dari satu tabel dibungkus `DB::transaction()` di **Service**.
- Hindari N+1: gunakan eager loading (`with()`) di Repository.
- Setiap tabel entitas yang diakses lewat CRUD wajib punya kolom `uuid` (`unique`) dan, bila perlu, `slug` (`unique`). `id` auto-increment hanya untuk internal (lihat bagian 5.1).
- Foreign key tetap memakai `id` di database; konversi dari/ke `uuid` dilakukan di Service/Repository, bukan di frontend.

---

## 10. Autentikasi (Aturan Tetap)

- Login email memakai **Fortify**; login Google memakai **Socialite**.
- Login (email maupun Google) **tidak boleh membuat user baru** jika akun belum terdaftar. Tampilkan error pada input email / tombol Google.
- Register adalah satu-satunya jalur pembuatan user baru.
- Setelah login: `session()->regenerate()`. Saat logout: `invalidate()` session + `regenerateToken()`.
- Password selalu di-hash (`Hash::make` / cast `hashed`). Jangan pernah log password, token, atau secret.
- Fitur **remember me** dikirim sebagai `remember` (boolean) dan diteruskan ke proses login.
- Rate limit login lewat Fortify.

---

## 11. Standar Kode

### PHP
- Ikuti **PSR-12**.
- Gunakan `declare(strict_types=1);` pada file baru.
- Type hint pada semua parameter dan return type. Gunakan `readonly` untuk properti hasil constructor promotion.
- Class `PascalCase`, method/variabel `camelCase`, konstanta `UPPER_SNAKE_CASE`, route name konsisten (`kebab`/`dot.notation`).
- Method publik diberi PHPDoc singkat jika tipe/maksudnya tidak jelas dari signature.
- Early return dibanding nested `if` yang dalam.
- Tidak ada logika bisnis di Controller, tidak ada query Eloquent di luar Repository, tidak ada status code hardcode di luar `ResponseHelper`.

### React / TypeScript
- Komponen `PascalCase`, file halaman mengikuti konvensi yang sudah ada di project.
- Props diberi tipe (jika TypeScript). Hindari `any`.
- Pisahkan komponen reusable ke `components/` bila dipakai lebih dari satu halaman.

### Umum
- Komentar hanya untuk menjelaskan **kenapa**, bukan mengulang **apa** yang dilakukan kode.
- Satu fungsi satu tanggung jawab. Hindari duplikasi (DRY), tetapi jangan membuat abstraksi prematur.
- Jangan ubah format/gaya kode di luar bagian yang dikerjakan.

---

## 12. Keamanan

- Selalu gunakan validasi Form Request; jangan percaya input user.
- Gunakan mass assignment protection; jangan `Model::create($request->all())`.
- Jangan membocorkan detail internal (exception, SQL, path) ke response.
- Terapkan otorisasi (Policy/Gate/middleware) pada setiap aksi yang membutuhkannya.
- Secret hanya di `.env`, diakses lewat `config()`. Jangan hardcode secret atau commit `.env`.
- Gunakan CSRF (bawaan Inertia/Laravel); jangan menonaktifkannya.
- Cegah IDOR: jangan ekspos `id` auto-increment ke frontend; pakai `uuid`/`slug` (bagian 5.1) **dan** tetap lakukan pemeriksaan kepemilikan/otorisasi di setiap aksi.
- Kirim data ke Inertia/JSON lewat API Resource dengan field eksplisit; jangan mengirim model mentah yang bisa membawa `id` atau kolom sensitif.

---

## 13. Checklist Sebelum Selesai

- [ ] Struktur folder mengikuti bagian 3; tidak ada file di tempat yang salah.
- [ ] Controller tipis; tidak ada query/logika bisnis di dalamnya.
- [ ] Service memakai Repository **interface**; binding terdaftar di `RepositoryServiceProvider`.
- [ ] Validasi memakai Form Request (backend) + validasi frontend.
- [ ] Semua controller memakai `ResponseHelper`.
- [ ] Tidak ada `return view` / `Inertia::render` manual di file route.
- [ ] Route resource memakai `{uuid}` / `{slug}` dengan constraint (`whereUuid` / `where`), bukan `{id}`.
- [ ] Tabel punya kolom `uuid` unique; model memakai `HasUuids`, `uniqueIds()`, `getRouteKeyName()`, dan `$hidden = ['id']`.
- [ ] Tidak ada `id` auto-increment di props Inertia, response JSON, URL, atau form; data dikirim lewat API Resource.
- [ ] Input relasi dari frontend memakai `*_uuid` dan diterjemahkan ke id di Service.
- [ ] Otorisasi/kepemilikan tetap dicek pada setiap aksi, tidak hanya mengandalkan UUID.
- [ ] Error validasi tampil sebagai state invalid pada input, bukan alert; tidak ada atribut `required` pada input dan `<form>` memakai `noValidate`.
- [ ] Halaman daftar memakai Data Table (TanStack) dengan pagination/sort/filter **server-side**, kolom sort di-whitelist, `getRowId` memakai `uuid`.
- [ ] Select dengan banyak opsi memakai Combobox reusable; value berupa `uuid`; state invalid berfungsi.
- [ ] Input file memakai `FileDropzone` (react-dropzone); batas tipe/ukuran sama dengan Form Request; nama file di-generate server; file lama dihapus saat diganti.
- [ ] Pesan success/error diputar lewat `notify` (sound `success.mp3` / `failure.mp3`), satu suara per event; flash ditangani `flash-handler`; tidak ada `new Audio` / `toast.*` langsung di komponen.
- [ ] Bentuk form sesuai aturan: modal untuk ≤ 4 field sederhana, form page untuk ≥ 5 field / ada upload / layout kompleks; modal tidak menutup saat error atau saat `processing`.
- [ ] Semua button async memakai `LoadingButton` (disabled + spinner saat loading), tanpa submit ganda.
- [ ] Nilai awal form kosong; tidak ada data dummy.
- [ ] Hanya komponen shadcn yang sudah terinstall yang dipakai.
- [ ] Tidak ada file/controller/route lama yang tertinggal dan tidak terpakai.
- [ ] `php artisan route:list` dan `npm run build` berjalan tanpa error.
- [ ] Ringkasan (asumsi, file berubah, perintah, hasil uji) sudah diberikan.

---

## 14. Format Jawaban Agent

1. **Asumsi** (jika ada)
2. **Daftar file:** dibuat / diubah / dihapus
3. **Kode lengkap** tiap file (atau langsung diterapkan ke project)
4. **Perintah** yang perlu dijalankan (`php artisan migrate`, `npm run build`, dll.)
5. **Hasil verifikasi** sesuai checklist bagian 13