<x-mail::message>
# Konfigurasi SMTP berhasil

Email ini dikirim dari {{ $appName }} untuk memastikan pengaturan email sudah benar.

<x-mail::table>
| Keterangan | Detail |
|:--|:--|
| Mailer | {{ $mailer }} |
| Host | {{ $host }} |
| Waktu kirim | {{ $sentAt }} |
</x-mail::table>

Salam,<br>
{{ $appName }}
</x-mail::message>
