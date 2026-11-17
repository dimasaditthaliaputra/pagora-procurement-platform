<x-mail::message>
# Vendor Disetujui

Yth. {{ $vendorName }},

Proses verifikasi dokumen legalitas perusahaan **{{ $companyName }}** telah disetujui oleh tim {{ config('app.name') }}.

Akun vendor Anda kini aktif. Silakan masuk untuk mulai mengelola kerja sama dan transaksi Anda.

<x-mail::button :url="route('login')">
Masuk ke {{ config('app.name') }}
</x-mail::button>

Penggunaan layanan tunduk pada [Syarat dan Ketentuan Penggunaan {{ config('app.name') }}]({{ url('/syarat-dan-ketentuan') }}).

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
