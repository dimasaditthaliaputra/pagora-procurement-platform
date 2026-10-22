<x-mail::message>
# Kode Verifikasi (OTP)

Gunakan kode berikut untuk {{ $purposeLabel }}.

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

Kode berlaku selama {{ $ttlMinutes }} menit. Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku dari {{ config('app.name') }}.

Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini.

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
