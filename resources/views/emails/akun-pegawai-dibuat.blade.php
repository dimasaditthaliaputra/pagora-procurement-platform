<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Bergabung & Kredensial Akun Internal</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.6; margin: 0; padding: 0; background-color: #f1f5f9;">
    <div style="max-width: 580px; margin: 32px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
        <!-- Header Banner -->
        <div style="background-color: #065f46; padding: 26px 32px; border-bottom: 3px solid #047857;">
            <p style="margin: 0 0 6px 0; color: #a7f3d0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                Platform Pengadaan PAGORA
            </p>
            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; letter-spacing: -0.2px;">
                Undangan Akses & Kredensial Pengguna Internal
            </h1>
        </div>

        <!-- Body Content -->
        <div style="padding: 32px;">
            <p style="margin-top: 0; font-size: 15px; font-weight: 600; color: #0f172a;">
                Yth. Bapak/Ibu {{ $user->name }},
            </p>

            <p style="font-size: 14px; color: #334155; margin-bottom: 20px;">
                Melalui surat elektronik ini, kami menginformasikan bahwa Anda telah didaftarkan sebagai pengguna internal pada sistem E-Procurement <strong>PAGORA</strong> oleh manajemen <strong>{{ $companyName }}</strong>.
            </p>

            <p style="font-size: 14px; color: #334155; margin-bottom: 24px;">
                Akun ini diberikan untuk mendukung pelaksanaan tugas operasional Anda sesuai dengan peran dan wewenang yang ditetapkan oleh perusahaan. Berikut adalah rincian kredensial akun Anda:
            </p>

            <!-- Credentials Box -->
            <div style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                    <tr>
                        <td style="padding: 6px 0; color: #64748b; width: 38%;">Perusahaan / Tenant:</td>
                        <td style="padding: 6px 0; font-weight: 600; color: #0f172a;">{{ $companyName }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Role / Jabatan Akses:</td>
                        <td style="padding: 6px 0; font-weight: 600; color: #065f46;">{{ $roleLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Alamat Email Masuk:</td>
                        <td style="padding: 6px 0; font-weight: 600; color: #0f172a;">{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0 6px 0; color: #64748b; vertical-align: top;">Kata Sandi Awal:</td>
                        <td style="padding: 8px 0 6px 0;">
                            <span style="display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 14.5px; font-weight: 700; color: #0f172a; background-color: #e2e8f0; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.5px; border: 1px solid #cbd5e1;">
                                {{ $defaultPassword }}
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Security & Instructions -->
            <div style="background-color: #f0fdf4; border-left: 4px solid #059669; padding: 14px 16px; margin-bottom: 28px; border-radius: 0 6px 6px 0;">
                <p style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 700; color: #166534;">
                    Petunjuk Aktivasi & Keamanan Akun:
                </p>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #14532d; line-height: 1.55;">
                    <li style="margin-bottom: 4px;">
                        <strong>Verifikasi OTP:</strong> Saat proses masuk (<em>login</em>) untuk pertama kali, sistem akan mengirimkan kode OTP ke email ini untuk memvalidasi identitas Anda.
                    </li>
                    <li style="margin-bottom: 4px;">
                        <strong>Pembaruan Kata Sandi:</strong> Demi menjaga keamanan data operasional perusahaan, Anda diwajibkan segera memperbarui kata sandi sementara ini melalui menu <em>Pengaturan Profil</em> setelah berhasil masuk.
                    </li>
                    <li>
                        <strong>Kerahasiaan:</strong> Mohon tidak meneruskan atau membagikan kredensial ini kepada pihak lain yang tidak berkepentingan.
                    </li>
                </ul>
            </div>

            <!-- CTA Button -->
            <div style="text-align: center; margin: 32px 0;">
                <a href="{{ $loginUrl }}"
                   style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 13px 36px; border-radius: 8px; font-size: 14.5px; font-weight: 600; letter-spacing: 0.2px; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2);">
                    Masuk ke Portal PAGORA
                </a>
            </div>

            <!-- Fallback URL -->
            <p style="font-size: 12.5px; color: #64748b; line-height: 1.5; margin-bottom: 24px;">
                Jika tombol di atas tidak dapat diklik, salin dan buka tautan berikut pada peramban web Anda:<br>
                <a href="{{ $loginUrl }}" style="color: #059669; word-break: break-all; text-decoration: underline;">{{ $loginUrl }}</a>
            </p>

            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

            <!-- Closing Signature -->
            <p style="font-size: 13px; color: #475569; margin: 0; line-height: 1.5;">
                Hormat kami,<br>
                <strong style="color: #0f172a;">Manajemen {{ $companyName }}</strong><br>
                <span style="color: #64748b;">Platform E-Procurement PAGORA</span>
            </p>
        </div>
    </div>
</body>
</html>

