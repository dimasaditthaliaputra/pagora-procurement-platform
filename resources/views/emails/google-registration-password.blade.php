<x-mail::message>
# Pendaftaran Akun Berhasil

Yth. **{{ $user->name }}**,

Pendaftaran akun Anda di **{{ config('app.name') }}** sebagai **{{ $accountType }}** telah berhasil diajukan menggunakan akun Google (**{{ $user->email }}**).

Karena Anda mendaftar melalui akun Google, email Anda telah otomatis terverifikasi tanpa memerlukan kode OTP. Anda dapat masuk ke dalam sistem sewaktu-waktu menggunakan tombol **Masuk dengan Google**, atau menggunakan kredensial kata sandi default unik berikut:

<x-mail::table>
| Keterangan | Rincian |
|:--|:--|
| Email Login | **{{ $user->email }}** |
| Kata Sandi Default | **{{ $defaultPassword }}** |
| Tipe Akun | {{ $accountType }} |
</x-mail::table>

> **Catatan Keamanan**: Demi keamanan akun, Anda dapat memperbarui kata sandi default ini kapan saja melalui menu pengaturan profil setelah masuk ke sistem. Harap tidak membagikan kredensial ini kepada pihak yang tidak berwenang.

<x-mail::button :url="$loginUrl">
Masuk ke {{ config('app.name') }}
</x-mail::button>

Dengan mendaftar atau menggunakan {{ config('app.name') }}, Anda menyetujui [Syarat dan Ketentuan Penggunaan {{ config('app.name') }}]({{ url('/syarat-dan-ketentuan') }}).

Salam hangat,<br>
Tim {{ config('app.name') }}
</x-mail::message>
