<!doctype html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #1b2420; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f5;">
    <div style="max-width: 560px; margin: 32px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div style="background-color: #059669; padding: 24px 32px;">
            <h2 style="margin: 0; color: #ffffff; font-size: 20px;">✅ Vendor Anda Telah Disetujui!</h2>
        </div>

        <div style="padding: 32px;">
            <p style="margin-top: 0;">Halo <strong>{{ $vendorName }}</strong>,</p>

            <p>
                Kami informasikan bahwa proses verifikasi dokumen legalitas
                perusahaan <strong>{{ $companyName }}</strong> telah disetujui
                oleh tim PAGORA.
            </p>

            <p>
                Akun vendor Anda sekarang aktif dan siap digunakan. Silakan masuk ke
                platform untuk mulai mengelola kerja sama dan transaksi Anda.
            </p>

            <div style="text-align: center; margin: 32px 0;">
                <a href="{{ url('/login') }}"
                   style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 15px; font-weight: 600; letter-spacing: 0.3px;">
                    Masuk ke PAGORA
                </a>
            </div>

            <p style="font-size: 13px; color: #6b7280;">
                Jika tombol di atas tidak berfungsi, salin dan buka tautan berikut di browser Anda:<br>
                <a href="{{ url('/login') }}" style="color: #059669; word-break: break-all;">{{ url('/login') }}</a>
            </p>

            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">

            <p style="margin-bottom: 0;">
                Salam,<br>
                <strong>Tim PAGORA</strong>
            </p>
        </div>
    </div>
</body>
</html>

