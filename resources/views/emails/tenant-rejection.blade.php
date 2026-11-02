<!doctype html>
<html lang="id">
<body style="font-family: Arial, sans-serif; color: #1b2420; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f5;">
    <div style="max-width: 560px; margin: 32px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div style="background-color: #dc2626; padding: 24px 32px;">
            <h2 style="margin: 0; color: #ffffff; font-size: 20px;">⚠️ Pendaftaran Tenant Memerlukan Perbaikan</h2>
        </div>

        <div style="padding: 32px;">
            <p style="margin-top: 0;">Halo <strong>{{ $ownerName }}</strong>,</p>

            <p>
                Terima kasih telah mendaftarkan perusahaan <strong>{{ $companyName }}</strong> ke platform PAGORA.
                Setelah dilakukan peninjauan, pendaftaran Anda saat ini belum dapat disetujui karena alasan berikut:
            </p>

            <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 0; color: #991b1b; font-weight: 500;">
                    {{ $rejectionReason }}
                </p>
            </div>

            <p>
                Silakan hubungi tim administrator platform atau lakukan pendaftaran ulang dengan berkas legalitas yang sesuai.
            </p>

            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">

            <p style="margin-bottom: 0;">
                Salam hangat,<br>
                <strong>Tim PAGORA Platform</strong>
            </p>
        </div>
    </div>
</body>
</html>

