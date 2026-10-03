# PAGORA — B2B Raw Material Procurement Platform

![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Inertia.js](https://img.shields.io/badge/Inertia.js-v3-9553E9?style=flat-square&logo=inertia&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?style=flat-square&logo=react&logoColor=black)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)

**PAGORA** adalah platform ekosistem pengadaan bahan baku B2B (*B2B Raw Material Procurement Platform*) yang dirancang untuk mengatasi inefisiensi transaksi pengadaan, asimetri pasar, serta fragmentasi operasional antara Purchasing, Vendor, Gudang (QC), dan Finance.

Dengan mengintegrasikan **Decision Support System (DSS)** berbasis harga acuan pasar, **ruang negosiasi terpandu**, otomasi **3-Way Matching**, serta mekanisme **Dispute Freezing**, PAGORA meminimalisir risiko kebocoran kas, mark-up anggaran belanja, dan sengketa mutu barang.

---

## 🎯 Masalah yang Diselesaikan

1. **Ketimpangan Pasar & Resiko Mark-Up**: Tim purchasing sering menghadapi asimetri informasi tanpa data pembanding pasar yang valid. PAGORA menyediakan batas Harga Pagu objektif sebelum Purchase Order (PO) diterbitkan.
2. **Silo Operasional Antar-Divisi**: Mencegah dokumen terpisah antara lembar PO (purchasing), surat jalan fisik (gudang), dan tagihan/faktur (finance) melalui integrasi data tersentralisasi.
3. **Sengketa Mutu Bahan Baku di Dock Gudang**: Mencegah tagihan barang cacat terproses bayar dengan fitur pembekuan pembayaran otomatis (*Dispute Freezing*) berbasis bukti inspeksi visual real-time.

---

## 🚀 Fitur Unggulan

- **Intelijen Harga Pagu (DSS)**: Penentuan batas atas harga wajar pengadaan berbasis data acuan historis dan tren regresi prediktif sebelum rilis PO.
- **Smart Negotiation Chat**: Ruang negosiasi tertutup (*closed bidding*) dengan efek anchoring berbasis Harga Pagu, dilengkapi penguncian parameter PO final (*auto-lock*) saat kesepakatan tercapai.
- **Otomasi 3-Way Matching**: Rekonsiliasi silang otomatis antara dokumen **Purchase Order (PO)**, **Good Receipt Note (GRN)** gudang, dan **Invoice Tagihan** vendor untuk memastikan kepatuhan audit dan mencegah penagihan ganda (*zero fraud verification*).
- **Inspeksi QC & Dispute Freezing**: Validasi fisik di dock bongkar muat gudang dengan kewajiban unggah foto bukti ketidaksesuaian yang secara otomatis menahan (*freeze*) pembayaran di finance.
- **Self-Reporting Logistics**: Pembaruan mandiri nomor armada dan estimasi kedatangan (ETA) langsung oleh vendor guna optimalisasi antrean dermaga gudang.
- **Tamper-Proof Audit Trail**: Riwayat aktivitas menyeluruh yang memfasilitasi kebutuhan audit operasional dan *Good Corporate Governance* (GCG).

---

## 🛠️ Tech Stack

| Layer | Teknologi |
| :--- | :--- |
| **Backend Framework** | [Laravel 13.x](https://laravel.com) (PHP ^8.3 / PHP 8.5) |
| **Monolith SPA Layer** | [Inertia.js v3](https://inertiajs.com) |
| **Frontend Framework** | [React 19](https://react.dev) |
| **Styling & Design System** | [Tailwind CSS v4](https://tailwindcss.com) |
| **UI Motion & Interaction** | [Framer Motion](https://www.framer.com/motion/), [GSAP](https://gsap.com), [Lenis](https://lenis.darkroom.engineering) |
| **Icons** | [Lucide React](https://lucide.dev) |
| **Build Tool** | [Vite 8](https://vitejs.dev) |
| **Quality & Testing** | [PHPUnit 12](https://phpunit.de), [Laravel Pint](https://laravel.com/docs/pint) |

---

## 📋 Prasyarat Sistem

Pastikan environment lokal telah memenuhi spesifikasi berikut:

- **PHP** `>= 8.3` (Rekomendasi: PHP 8.4+)
  - Ekstensi yang diperlukan: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql` (atau `pdo_sqlite`), `tokenizer`, `xml`
- **Composer** `>= 2.7`
- **Node.js** `>= 20.x` & **npm** `>= 10.x`
- **Database Engine**: MySQL `>= 8.0` / MariaDB `>= 10.4` / SQLite (untuk testing lokal)

---

## ⚙️ Panduan Instalasi

### 1. Kloning Repositori
```bash
git clone https://github.com/dimasaditthaliaputra/pagora-procurement-platform.git pagora-website
cd pagora-website
```

### 2. Konfigurasi Environment
Salin berkas konfigurasi template `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Sesuaikan konfigurasi database dan kredensial aplikasi pada berkas `.env`:
```dotenv
APP_NAME=PAGORA
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pagora_db
DB_USERNAME=root
DB_PASSWORD=secret
```

### 3. Instalasi Dependensi Backend
```bash
composer install
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Migrasi & Seeding Database
Jalankan migrasi database:
```bash
php artisan migrate
```
*(Opsional: Tambahkan `--seed` jika Anda telah mendefinisikan seeder dummy)*

### 6. Instalasi Dependensi Frontend
```bash
npm install
```

> **Alternatif Cepat (Automated Setup Script):**
> Anda juga dapat menjalankan seluruh alur persiapan awal dalam satu perintah:
> ```bash
> composer run setup
> ```

---

## 💻 Menjalankan Aplikasi

### Mode Pengembangan (Development)

Untuk menjalankan server backend Laravel dan server Vite frontend secara terintegrasi:

```bash
composer run dev
```
*(Perintah ini memanfaatkan fitur proses konkuren bawaan `@php artisan dev`)*

Atau jalankan pada dua sesi terminal terpisah:

**Terminal 1 — Backend Server:**
```bash
php artisan serve
```

**Terminal 2 — Vite Dev Server:**
```bash
npm run dev
```

Akses aplikasi pada peramban melalui alamat: **`http://localhost:8000`**

---

### Mode Produksi (Production Build)

Kompilasi asset frontend untuk rilis produksi:
```bash
npm run build
```

Optimalisasi cache backend:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🧪 Testing & Code Quality

### Menjalankan Unit & Feature Tests
```bash
composer test
# atau
php artisan test
```

### Pemformatan Kode (Code Style)
Standardisasi kode PHP menggunakan Laravel Pint:
```bash
vendor/bin/pint --format agent
```

---

## 📁 Struktur Direktori Penting

```text
pagora-website/
├── app/
│   ├── Http/Controllers/    # Controller aplikasi & endpoint Inertia
│   └── Models/              # Eloquent Model & relasi domain bisnis
├── config/                  # Konfigurasi sistem Laravel
├── database/
│   ├── factories/           # Factory data model
│   ├── migrations/          # Skema migrasi basis data
│   └── seeders/             # Database seeders
├── resources/
│   ├── css/                 # Stylesheet Tailwind CSS v4
│   └── js/
│       ├── Components/      # Komponen UI React reusable
│       ├── Pages/           # Tampilan antarmuka Inertia (Home, Auth, Portal)
│       ├── data/            # Mock dataset & struktur data portal
│       └── app.jsx          # Entry point aplikasi React & Inertia
├── routes/
│   ├── web.php              # Rute aplikasi web & otentikasi
│   └── console.php          # Perintah CLI Artisan kustom
└── tests/                   # Test suite (Unit & Feature)
```

---
