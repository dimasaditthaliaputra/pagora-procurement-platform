<x-mail::message>
# Akun Tenant Disetujui

Yth. {{ $ownerName }},

Proses verifikasi profil dan dokumen legalitas perusahaan **{{ $companyName }}** telah disetujui oleh tim {{ config('app.name') }}.

Tenant Anda kini aktif. Silakan masuk untuk mulai mengelola pengadaan, mengundang rekan kerja, dan bertransaksi dengan vendor.

<x-mail::button :url="route('login')">
Masuk ke {{ config('app.name') }}
</x-mail::button>

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
