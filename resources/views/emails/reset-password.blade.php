<x-mail::message>
# Atur Ulang Kata Sandi

Yth. {{ $name }},

Kami menerima permintaan untuk mengatur ulang kata sandi akun {{ config('app.name') }} Anda.

<x-mail::button :url="$url">
Atur Ulang Kata Sandi
</x-mail::button>

Tautan ini berlaku selama {{ $expireMinutes }} menit. Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini. Kata sandi Anda tidak akan berubah.

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
