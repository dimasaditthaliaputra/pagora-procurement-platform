<!doctype html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #1b2420; line-height: 1.5;">
    <h2>Konfigurasi SMTP berhasil</h2>
    <p>Email ini dikirim dari <strong>{{ $appName }}</strong> untuk memastikan pengaturan email sudah benar.</p>
    <ul>
        <li>Mailer: {{ $mailer }}</li>
        <li>Host: {{ $host }}</li>
        <li>Waktu kirim: {{ $sentAt }}</li>
    </ul>
</body>
</html>
