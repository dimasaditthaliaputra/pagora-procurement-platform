<x-mail::message>
# Pendaftaran Tenant Belum Disetujui

Yth. {{ $ownerName }},

Terima kasih telah mendaftarkan perusahaan **{{ $companyName }}** di {{ config('app.name') }}. Setelah peninjauan, pendaftaran Anda belum dapat disetujui dengan alasan berikut:

<x-mail::panel>
{{ $rejectionReason }}
</x-mail::panel>

Silakan hubungi administrator platform atau lakukan pendaftaran ulang dengan dokumen legalitas yang sesuai.

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
