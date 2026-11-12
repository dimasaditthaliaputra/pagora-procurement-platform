<x-mail::message>
# Undangan Pendaftaran Vendor

Yth. Perwakilan {{ $companyName }},

{{ $inviterName }} ({{ $inviterRole }}) mengundang perusahaan **{{ $companyName }}** untuk bergabung sebagai calon vendor pada platform E-Procurement {{ config('app.name') }}.

@if ($commodityCategory)
**Kategori komoditas:** {{ $commodityCategory }}

@endif
@if ($notes)
<x-mail::panel>
{{ $notes }}
</x-mail::panel>

@endif
Silakan klik tombol berikut untuk melengkapi pendaftaran. Tautan ini berlaku selama 7 hari sejak undangan dibuat.

<x-mail::button :url="$registrationUrl">
Daftar sebagai Vendor
</x-mail::button>

Jika Anda tidak merasa terkait dengan undangan ini, abaikan email ini.

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
