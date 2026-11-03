
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Pendaftaran Vendor PAGORA</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #333333; font-size: 14px; line-height: 1.7; margin: 0; padding: 24px; background-color: #ffffff;">

    <p>Yth. Bapak/Ibu Perwakilan {{ $invitation->nama_perusahaan }},</p>

    <p>Dengan hormat,</p>

    <p>
        Melalui email ini, kami bermaksud mengundang perusahaan
        <strong>{{ $invitation->nama_perusahaan }}</strong>
        untuk bergabung sebagai calon vendor pada platform
        E-Procurement <strong>PAGORA</strong>.
    </p>

    <p>
        Kami berharap perusahaan Bapak/Ibu dapat berpartisipasi
        dan menjalin kerja sama dalam memenuhi kebutuhan pengadaan
        bahan baku melalui platform PAGORA.
    </p>

    @if ($invitation->kategori_komoditas)
        <p>
            <strong>Kategori Komoditas:</strong><br>
            {{ $invitation->kategori_komoditas }}
        </p>
    @endif

    @if ($invitation->pesan)
        <p>
            <strong>Pesan Undangan:</strong><br>
            {{ $invitation->pesan }}
        </p>
    @endif

    <p>
        Untuk melanjutkan proses pendaftaran dan melengkapi
        informasi perusahaan, silakan klik tombol berikut:
    </p>

    <p style="margin: 24px 0;">
        <a href="{{ $registrationUrl }}"
           style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 5px; font-weight: bold;">
            Daftar sebagai Vendor
        </a>
    </p>

    <p>
        Apabila tombol di atas tidak dapat digunakan, Bapak/Ibu
        dapat membuka tautan pendaftaran berikut melalui browser:
    </p>

    <p>
        <a href="{{ $registrationUrl }}" style="color: #059669; word-break: break-all;">
            {{ $registrationUrl }}
        </a>
    </p>

    <p>
        Tautan undangan ini berlaku selama 7 hari sejak undangan dibuat.
    </p>

    <p>
        Demikian undangan ini kami sampaikan. Atas perhatian dan
        kesediaan Bapak/Ibu, kami mengucapkan terima kasih.
    </p>

    <p>
        Hormat kami,<br>
        <strong>Tim PAGORA</strong>
    </p>

</body>
</html>
