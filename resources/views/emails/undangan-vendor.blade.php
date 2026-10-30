<!doctype html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #1b2420; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f5;">
    <div style="max-width: 560px; margin: 32px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div style="background-color: #059669; padding: 24px 32px;">
            <h2 style="margin: 0; color: #ffffff; font-size: 20px;">✉️ Undangan Kemitraan Rekanan Vendor</h2>
        </div>

        <div style="padding: 32px;">
            <p style="margin-top: 0;">Halo Tim <strong>{{ $companyName }}</strong>,</p>

            <p>
                Anda secara resmi diundang oleh <strong>{{ $inviterName }}</strong> ({{ $inviterRole }})
                untuk bergabung sebagai rekanan vendor mitra pada platform E-Procurement <strong>PAGORA</strong>.
            </p>

            @if (!empty($commodityCategory))
                <div style="background-color: #f0fdf4; border-left: 4px solid #059669; padding: 12px 16px; margin: 20px 0; border-radius: 4px;">
                    <p style="margin: 0; font-size: 14px; color: #166534;">
                        <strong>Kategori Komoditas yang Diajukan:</strong><br>
                        {{ $commodityCategory }}
                    </p>
                </div>
            @endif

            @if (!empty($notes))
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 14px 16px; margin: 20px 0; border-radius: 6px;">
                    <p style="margin: 0; font-size: 13px; color: #475569;">
                        <strong>Pesan / Catatan Undangan:</strong><br>
                        <span style="font-style: italic;">"{{ $notes }}"</span>
                    </p>
                </div>
            @endif

            <p>
                Untuk memulai proses registrasi serta melengkapi data dan dokumen legalitas perusahaan Anda, silakan klik tombol di bawah ini:
            </p>

            <div style="text-align: center; margin: 32px 0;">
                <a href="{{ $registrationUrl }}"
                   style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-size: 15px; font-weight: 600; letter-spacing: 0.3px;">
                    Daftar Sebagai Vendor
                </a>
            </div>

            <p style="font-size: 13px; color: #6b7280; line-height: 1.5;">
                ⏰ <em>Catatan: Tautan undangan ini berlaku selama 7 hari kalender.</em>
            </p>

            <p style="font-size: 13px; color: #6b7280;">
                Jika tombol di atas tidak berfungsi, salin dan buka tautan berikut di browser Anda:<br>
                <a href="{{ $registrationUrl }}" style="color: #059669; word-break: break-all;">{{ $registrationUrl }}</a>
            </p>

            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">

            <p style="margin-bottom: 0;">
                Salam hangat,<br>
                <strong>Tim PAGORA</strong>
            </p>
        </div>
    </div>
</body>
</html>
