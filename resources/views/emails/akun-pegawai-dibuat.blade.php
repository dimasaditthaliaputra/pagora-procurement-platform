<x-mail::message>
# Akun Internal Anda Telah Dibuat

Yth. {{ $user->name }},

Anda telah didaftarkan sebagai pengguna internal {{ config('app.name') }} oleh manajemen **{{ $companyName }}**. Berikut rincian akun Anda:

<x-mail::table>
| Keterangan | Detail |
|:--|:--|
| Perusahaan | {{ $companyName }} |
| Peran | {{ $roleLabel }} |
| Email | {{ $user->email }} |
| Kata sandi sementara | **{{ $defaultPassword }}** |
</x-mail::table>

Saat masuk pertama kali, sistem akan mengirimkan kode OTP ke email ini. Segera ganti kata sandi sementara setelah berhasil masuk, dan jangan bagikan kredensial ini kepada siapa pun.

<x-mail::button :url="$loginUrl">
Masuk ke {{ config('app.name') }}
</x-mail::button>

Salam,<br>
Manajemen {{ $companyName }}
</x-mail::message>
