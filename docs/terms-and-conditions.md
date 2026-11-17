# Syarat dan Ketentuan Penggunaan PAGORA

**Versi:** 1.0  
**Berlaku sejak:** {{TANGGAL_BERLAKU}}  
**Penyelenggara:** {{NAMA_PENYELENGGARA}}  
**Kontak:** {{EMAIL_KONTAK}}

Dengan mendaftar atau menggunakan PAGORA, Anda menyetujui Syarat dan Ketentuan (S&K) berikut.

## 1. Definisi

1.1 "PAGORA" adalah aplikasi web portal e-procurement B2B multi-tenant untuk pengadaan bahan baku industri yang diselenggarakan oleh {{NAMA_PENYELENGGARA}} ("Penyelenggara").

1.2 "Tenant" adalah perusahaan pembeli yang terdaftar di PAGORA.

1.3 "Vendor" adalah perusahaan pemasok yang terdaftar di PAGORA.

1.4 "Pengguna" adalah setiap orang yang memiliki akun PAGORA, yaitu Superadmin, Owner, Manajemen, Purchasing/Finance, QC Gudang, atau Vendor.

1.5 "Superadmin" adalah pengelola platform PAGORA yang memverifikasi pendaftaran dan mengelola data master.

1.6 "Owner" adalah pengguna yang mendaftarkan Tenant dan mengelola perusahaan serta anggota internalnya.

1.7 "Purchase Order" atau "PO" adalah dokumen pemesanan barang dari Tenant kepada Vendor di PAGORA.

1.8 "Harga Pagu" adalah rekomendasi batas harga wajar tertinggi yang dihitung PAGORA dengan Regresi Linier dari data API Harga Pangan Bank Indonesia.

1.9 "3-Way Matching" adalah pencocokan otomatis per item antara PO, penerimaan barang di gudang, dan invoice.

1.10 "Sengketa" adalah perselisihan atas suatu transaksi yang dicatat di PAGORA beserta "Log Sengketa"-nya.

1.11 "Payment Gateway" adalah layanan pembayaran pihak ketiga yang terintegrasi dengan PAGORA.

## 2. Ruang Lingkup dan Sifat Layanan

2.1 PAGORA menyediakan sarana digital untuk siklus pengadaan: kemitraan, Purchase Order, negosiasi harga per item, pelaporan pengiriman, inspeksi barang, penerbitan invoice, verifikasi tagihan, pembayaran, dan pengelolaan sengketa.

2.2 PAGORA hanya berperan sebagai penyedia platform. Perjanjian jual beli dibuat langsung antara Tenant dan Vendor. Penyelenggara bukan pihak dalam transaksi tersebut dan tidak menjamin kualitas, kuantitas, keaslian, maupun pengiriman barang.

2.3 PAGORA berupa aplikasi web responsif. Tidak ada aplikasi mobile native.

2.4 Status pengiriman dicatat secara manual oleh Vendor. PAGORA tidak terhubung dengan sistem pelacakan kurir dan tidak menjamin ketepatan data pengiriman yang dilaporkan Vendor.

2.5 PAGORA tidak menyediakan paket langganan berbayar untuk Tenant pada saat S&K ini berlaku. Bila ketentuan biaya dikenakan di kemudian hari, ketentuan itu diberlakukan melalui perubahan S&K sesuai Pasal 21.

## 3. Pendaftaran dan Verifikasi Akun

3.1 Pendaftaran Tenant dan Vendor hanya untuk badan usaha yang sah dan diwakili oleh orang yang berwenang. Dengan mendaftar, Anda menyatakan berwenang mengikat perusahaan Anda pada S&K ini.

3.2 Pendaftaran dapat dilakukan dengan email bisnis dan kata sandi, atau dengan akun Google. Pada pendaftaran manual, email bisnis wajib diverifikasi dengan kode OTP 6 digit. Pada pendaftaran dengan Google, identitas dan email diverifikasi melalui Google OAuth, dan PAGORA membuat kata sandi default yang dikirim ke email Google Anda. Anda disarankan segera menggantinya.

3.3 Tenant wajib menyerahkan nama perusahaan, NPWP, nomor telepon, alamat, dan dokumen legalitas (NPWP atau Surat Izin Usaha). Vendor wajib menyerahkan nama perusahaan, NPWP, nomor telepon, alamat, minimal satu kategori komoditas, dan dokumen legalitas (NPWP wajib; SIUP/NIB opsional).

3.4 Berkas unggahan berformat sesuai ketentuan yang tampil di form dan berukuran maksimal 5 MB per berkas.

3.5 Data yang Anda berikan harus benar, lengkap, dan terkini. Anda bertanggung jawab atas keakuratannya dan wajib memperbaruinya bila berubah.

3.6 Setiap pendaftaran Tenant dan legalitas Vendor diverifikasi Superadmin. Sebelum disetujui, akun tidak dapat digunakan untuk login. Superadmin dapat menolak pendaftaran dengan menyebutkan alasan melalui email.

3.7 Vendor dapat mendaftar secara mandiri atau melalui tautan undangan dari Tenant. Tautan undangan bersifat unik, memiliki masa berlaku, dan tidak boleh dialihkan kepada pihak lain.

## 4. Peran dan Hak Akses

4.1 Hak akses ditentukan oleh peran: Superadmin, Owner, Manajemen, Purchasing/Finance, QC Gudang, dan Vendor. Anda hanya boleh menggunakan fitur yang tersedia untuk peran Anda.

4.2 Owner dapat menambahkan anggota internal dengan peran Manajemen, Purchasing/Finance, atau QC Gudang, melalui akun langsung dengan kata sandi default atau melalui undangan email. Owner tidak dapat memberikan peran Superadmin.

4.3 Owner bertanggung jawab atas seluruh tindakan anggota internal Tenant-nya di PAGORA, termasuk pemberian, perubahan, dan pencabutan akses.

4.4 Data antar-Tenant terisolasi. Vendor tidak dapat mengakses halaman internal Tenant, dan pengguna hanya dapat mengakses PO yang terkait dengannya. Superadmin dapat melihat data lintas Tenant sebatas untuk pengelolaan platform.

4.5 Tindakan di PAGORA dicatat dan dikaitkan dengan akun yang melakukannya.

## 5. Keamanan Akun dan Sesi

5.1 Anda wajib menjaga kerahasiaan kata sandi dan akses ke akun Google yang terhubung. Setiap aktivitas yang dilakukan melalui akun Anda dianggap dilakukan oleh Anda.

5.2 Segera hubungi {{EMAIL_KONTAK}} bila Anda menduga akun Anda digunakan tanpa izin.

5.3 Sesi login berakhir otomatis setelah 30 menit tidak aktif. Fitur "Ingat Saya" berlaku paling lama 30 hari. Sesi dapat diputus paksa bila akun diblokir.

5.4 Tautan reset kata sandi berlaku 60 menit dan hanya dapat digunakan sekali.

5.5 Login ditolak bila akun tidak aktif atau dihapus, Tenant atau Vendor belum disetujui verifikasinya, atau Vendor diblokir secara global.

## 6. Kerja Sama dan Pemblokiran Vendor

6.1 Purchase Order hanya dapat dibuat kepada Vendor yang berstatus kerja sama "Disepakati" dengan Tenant dan tidak sedang diblokir.

6.2 Pengajuan kerja sama dapat diterima atau ditolak oleh pihak yang diajak. Penolakan disertai catatan alasan.

6.3 Kerja sama tidak dapat dibatalkan selama masih ada PO yang belum selesai atau dibatalkan, atau masih ada Sengketa terbuka.

6.4 Tenant dapat memblokir Vendor dengan menyebutkan alasan. Pemblokiran per Tenant tidak menghentikan PO yang sedang berjalan, tetapi PO baru kepada Vendor tersebut tidak dapat dibuat sampai blokir dibuka.

6.5 Superadmin dapat memblokir Vendor secara global, yang mencabut akses dan memutus seluruh sesi akun Vendor. Alasan, waktu, dan pelaku pemblokiran dicatat.

## 7. Harga Pagu dan Analitik Harga

7.1 Harga Pagu dihitung dari data API Harga Pangan Bank Indonesia dengan metode Regresi Linier. Harga Pagu hanyalah alat bantu pengambilan keputusan, bukan penawaran harga, jaminan harga, atau saran keuangan. Keputusan harga sepenuhnya ada pada Tenant.

7.2 Hasil prediksi dapat tidak akurat, terutama bila data historis terbatas. Bila API Bank Indonesia tidak dapat diakses, PAGORA menampilkan data terakhir yang tersimpan beserta waktu pembaruannya. Prediksi tidak tersedia untuk komoditas yang tidak ada di API tersebut.

7.3 Harga Pagu dan estimasi harga internal Tenant hanya terlihat oleh pihak Tenant. Keduanya tidak ditampilkan atau dikirim kepada Vendor.

## 8. Purchase Order dan Negosiasi

8.1 PO dapat memuat satu atau lebih item. Estimasi harga satuan hanya acuan anggaran. Harga final per item ditentukan dari hasil negosiasi.

8.2 Draf PO dapat diubah atau dihapus selama berstatus Draft. PO dapat dibatalkan selama belum berstatus Disepakati, dan pembatalan wajib disertai alasan yang disimpan pada PO.

8.3 Negosiasi dilakukan per item melalui Smart Negotiation Chat yang terikat pada satu PO. Item yang tidak disepakati dikeluarkan dari PO. Bila tidak ada item tersisa, PO berstatus Dibatalkan.

8.4 Setelah seluruh item tersisa disepakati, PO berstatus Disepakati. Dokumen dan harga final terkunci, dan chat menjadi hanya-baca. Persetujuan harga di PAGORA oleh pengguna yang berwenang mengikat Tenant atau Vendor yang diwakilinya.

8.5 Tenant hanya dapat memesan komoditas yang diaktifkannya di PAGORA.

## 9. Pengiriman dan Inspeksi Barang

9.1 Vendor wajib mengisi form pengiriman secara benar, yaitu jalur pengiriman, nama armada, nomor plat, dan perkiraan tanggal tiba. Setelah form disimpan, kuantitas PO terkunci dan tidak dapat diubah.

9.2 Inspeksi dilakukan per item oleh QC Gudang atau Owner pada PO berstatus Dalam Pengiriman. Item yang sesuai berstatus Disetujui.

9.3 Item yang tidak sesuai berstatus Ditolak dan wajib disertai catatan serta minimal satu foto bukti (maksimal 5 MB per berkas). Penolakan membuka Sengketa untuk item tersebut dan tidak membatalkan PO.

9.4 Foto bukti inspeksi tidak dapat diubah atau dihapus setelah disimpan.

## 10. Invoice, Verifikasi Tagihan, dan Pembayaran

10.1 Vendor hanya dapat menerbitkan invoice untuk item yang lolos inspeksi dan belum ditagih. Satu PO dapat memiliki lebih dari satu invoice.

10.2 Setiap invoice diverifikasi dengan 3-Way Matching per item terhadap kuantitas dan harga final PO, kuantitas yang diterima gudang, serta kuantitas dan nominal invoice.

10.3 Bila hasil pencocokan sesuai, PO berstatus Siap Dibayar dan pembayaran dapat diproses melalui Payment Gateway. Jumlah yang dibayar adalah nilai tagihan dikurangi credit note yang sudah dikonfirmasi Vendor.

10.4 Bila hasil pencocokan tidak sesuai, pembayaran ditolak dan PAGORA otomatis membuka Sengketa. Selama Sengketa terbuka, PO dibekukan.

10.5 Pembayaran diproses oleh penyedia Payment Gateway pihak ketiga dan tunduk pada syarat penyedia tersebut. Penyelenggara tidak menyimpan data kartu atau rekening Anda dan tidak bertanggung jawab atas gangguan atau kegagalan layanan penyedia pembayaran. Status pembayaran di PAGORA mengikuti notifikasi (callback) dari penyedia tersebut.

10.6 Bila pembayaran gagal, pembayaran dapat dicoba kembali. Satu invoice dinyatakan lunas bila total pembayaran berhasil sama dengan nilai tagihan dikurangi credit note yang dikonfirmasi.

10.7 Pajak, bea, dan kewajiban perpajakan atas transaksi menjadi tanggung jawab masing-masing pihak sesuai peraturan perundang-undangan.

## 11. Sengketa

11.1 Sengketa dibuka otomatis ketika barang ditolak saat inspeksi atau 3-Way Matching tidak sesuai. Owner, Manajemen, atau Purchasing dapat membuka Sengketa secara manual bila Tenant dan Vendor tidak menyepakati penyelesaian transaksi, dengan menyertakan alasan dan bukti.

11.2 Sengketa memiliki status tersendiri yang terpisah dari status PO. Selama Sengketa terbuka, PO dibekukan pada bagian yang terdampak.

11.3 Peninjauan dan mediasi dilakukan oleh Owner atau Manajemen Tenant. Penyelenggara tidak bertindak sebagai hakim, arbiter, atau penjamin atas hasil penyelesaian.

11.4 Penyelesaian dapat berupa pengiriman ulang, retur barang, atau potongan tagihan melalui credit note. Penyelesaian yang membutuhkan tindakan Vendor hanya berlaku setelah Vendor mengonfirmasinya.

11.5 Vendor dapat menanggapi dan mengonfirmasi penyelesaian, tetapi tidak dapat mengubah status Sengketa. Status Selesai hanya dapat ditetapkan oleh Owner atau Manajemen setelah penyelesaian diverifikasi. Bila Vendor tidak mengonfirmasi, Sengketa kembali ke tahap mediasi.

11.6 Setelah Sengketa selesai, PO dilanjutkan dari progres terakhirnya.

11.7 Dokumen Sengketa dapat diunduh oleh pihak yang berhak. Para pihak boleh menempuh jalur penyelesaian lain di luar PAGORA sesuai Pasal 22.

## 12. Catatan Permanen dan Penghapusan Data

12.1 Chat negosiasi, foto inspeksi, harga PO yang terkunci, Log Sengketa, Sengketa yang selesai, serta credit note dan retur yang sudah dikonfirmasi bersifat permanen. Catatan tersebut tidak dapat diubah atau dihapus oleh siapa pun, termasuk Superadmin, sebagai bukti transaksi yang sah.

12.2 Penghapusan anggota, komoditas, draf PO, dan pemblokiran Vendor dilakukan dengan penghapusan lunak (soft delete) atau perubahan status, sehingga riwayat transaksi tetap utuh.

12.3 Karena itu, permintaan penghapusan data pribadi atau transaksi dapat dibatasi sepanjang data tersebut diperlukan untuk bukti transaksi, penyelesaian Sengketa, atau kewajiban hukum.

## 13. Dokumen dan Konten yang Anda Unggah

13.1 Anda tetap memiliki hak atas dokumen dan konten yang Anda unggah, termasuk dokumen legalitas, foto inspeksi, invoice, bukti pembayaran, dan bukti Sengketa.

13.2 Anda memberi Penyelenggara izin terbatas, non-eksklusif, untuk menyimpan, memproses, dan menampilkan konten tersebut kepada pihak yang berhak di PAGORA, hanya untuk menjalankan layanan.

13.3 Anda menjamin bahwa konten yang diunggah benar, tidak dipalsukan, dan tidak melanggar hak pihak lain atau hukum yang berlaku.

## 14. Larangan Penggunaan

14.1 Memberikan data, dokumen, atau identitas yang palsu atau menyesatkan.

14.2 Mengakses akun, data, atau PO milik pihak lain tanpa hak, atau mencoba melewati pembatasan hak akses dan isolasi data.

14.3 Mengganggu atau merusak layanan, antara lain dengan malware, serangan beban berlebih, pemindaian kerentanan tanpa izin, atau rekayasa balik.

14.4 Mengambil data PAGORA secara massal (scraping) atau menggunakan layanan ini untuk tujuan di luar pengadaan bisnis yang sah.

14.5 Menyalahgunakan Harga Pagu, misalnya membagikannya kepada Vendor atau pihak luar di luar Tenant.

14.6 Menyebarkan konten yang melanggar hukum, mengandung kebencian, atau melanggar hak pihak lain di chat, catatan, atau berkas unggahan.

14.7 Menggunakan layanan untuk pencucian uang, penipuan, atau kegiatan melawan hukum lainnya.

## 15. Data Pribadi dan Privasi

15.1 Penyelenggara memproses data seperti nama, email, peran, data perusahaan, NPWP, dokumen legalitas, aktivitas transaksi, dan log aktivitas, untuk menjalankan layanan, memverifikasi pendaftaran, mengamankan platform, dan memenuhi kewajiban hukum.

15.2 Pada login dengan Google, PAGORA menerima identitas dasar akun Google Anda, yaitu nama dan alamat email, untuk mendaftarkan atau mengautentikasi akun Anda.

15.3 Data yang relevan dibagikan kepada pihak lain di transaksi yang sama (misalnya Tenant dan Vendor pada PO yang sama), kepada penyedia layanan yang membantu operasional (seperti penyedia email, penyimpanan, Payment Gateway, dan Google OAuth), serta kepada pihak berwenang bila diwajibkan hukum. Penyelenggara tidak menjual data Anda.

15.4 Kata sandi disimpan dalam bentuk hash. Berkas unggahan disimpan di lokasi yang tidak dapat dieksekusi. Kredensial layanan pihak ketiga disimpan terpisah dari kode aplikasi.

15.5 Data dilindungi dengan langkah keamanan yang wajar, tetapi tidak ada sistem yang sepenuhnya bebas risiko. Penyelenggara akan menindaklanjuti insiden keamanan sesuai peraturan yang berlaku.

15.6 Sesuai peraturan pelindungan data pribadi yang berlaku di Indonesia, Anda berhak meminta akses, perbaikan, dan penghapusan data pribadi Anda, dengan batasan pada Pasal 12. Ajukan permintaan ke {{EMAIL_KONTAK}}.

15.7 Anda menjamin bahwa data pribadi pihak lain yang Anda masukkan, misalnya data anggota tim, telah Anda peroleh secara sah dan Anda berwenang membagikannya.

## 16. Notifikasi dan Komunikasi

16.1 PAGORA mengirim notifikasi di dalam aplikasi dan melalui email untuk hal-hal seperti verifikasi akun, kode OTP, undangan, reset kata sandi, perkembangan PO, kerja sama, dan Sengketa.

16.2 Anda wajib menjaga agar alamat email Anda aktif. Pemberitahuan yang dikirim ke email terdaftar dianggap telah diterima.

## 17. Ketersediaan Layanan

17.1 Penyelenggara menargetkan ketersediaan layanan minimal 99% pada Senin sampai Sabtu pukul 07.00 sampai 18.00 WIB. Ini adalah target, bukan jaminan.

17.2 Pemeliharaan terjadwal diupayakan di luar jam operasional tersebut. Data dicadangkan otomatis minimal sekali sehari.

17.3 Layanan dapat terganggu karena hal di luar kendali Penyelenggara, termasuk gangguan jaringan, gangguan penyedia pihak ketiga (API Bank Indonesia, Payment Gateway, email, Google), atau keadaan memaksa.

17.4 Layanan dirancang untuk peramban terbaru (Chrome, Firefox, Edge, Safari) melalui koneksi HTTPS.

## 18. Hak Kekayaan Intelektual

18.1 Seluruh hak atas PAGORA, termasuk perangkat lunak, desain, merek, dan dokumentasi, dimiliki Penyelenggara atau pemberi lisensinya.

18.2 Anda diberi hak terbatas, tidak eksklusif, dan tidak dapat dialihkan untuk menggunakan PAGORA selama akun Anda aktif, sesuai S&K ini.

## 19. Batasan Tanggung Jawab

19.1 Layanan disediakan sebagaimana adanya dan sebagaimana tersedia. Sejauh diizinkan hukum, Penyelenggara tidak bertanggung jawab atas kerugian tidak langsung, kehilangan keuntungan, atau kehilangan peluang usaha yang timbul dari penggunaan atau ketidakmampuan menggunakan PAGORA.

19.2 Penyelenggara tidak bertanggung jawab atas: (a) kualitas, jumlah, atau pengiriman barang; (b) kesepakatan harga dan keputusan bisnis para pihak, termasuk keputusan berdasarkan Harga Pagu; (c) kebenaran data dan dokumen yang diberikan pengguna; (d) hasil penyelesaian Sengketa; (e) gangguan pada layanan pihak ketiga; atau (f) tindakan pengguna yang melanggar S&K ini.

19.3 Sejauh diizinkan hukum, total tanggung jawab Penyelenggara kepada Anda atas klaim apa pun terkait layanan dibatasi pada biaya yang telah Anda bayarkan kepada Penyelenggara untuk layanan dalam 12 bulan terakhir, atau nol bila layanan tidak berbayar.

19.4 Ketentuan ini tidak membatasi tanggung jawab yang menurut hukum tidak dapat dibatasi.

## 20. Penangguhan dan Penghentian Akun

20.1 Penyelenggara dapat menangguhkan atau memblokir akun, menolak verifikasi, atau menghentikan akses bila Anda melanggar S&K ini, memberikan data palsu, atau bila diwajibkan hukum, dengan memberitahukan alasannya bila memungkinkan.

20.2 Anda dapat berhenti menggunakan PAGORA kapan saja dengan menghubungi {{EMAIL_KONTAK}}, setelah transaksi dan Sengketa yang sedang berjalan diselesaikan.

20.3 Penghentian akun tidak menghapus catatan permanen sesuai Pasal 12, dan tidak menghapus kewajiban yang telah timbul sebelum penghentian.

20.4 Pasal yang menurut sifatnya tetap berlaku setelah penghentian, termasuk Pasal 12, 13, 18, 19, dan 22, tetap berlaku.

## 21. Perubahan S&K

21.1 Penyelenggara dapat mengubah S&K ini dari waktu ke waktu. Perubahan material diberitahukan melalui email atau notifikasi di aplikasi, dengan tanggal berlaku yang jelas.

21.2 Bila Anda terus menggunakan PAGORA setelah tanggal berlaku perubahan, Anda dianggap menyetujui S&K yang baru. Bila tidak setuju, Anda berhak berhenti menggunakan layanan sesuai Pasal 20.

## 22. Hukum yang Berlaku dan Penyelesaian Perselisihan

22.1 S&K ini diatur oleh hukum Republik Indonesia.

22.2 Perselisihan antara Anda dan Penyelenggara diselesaikan terlebih dahulu secara musyawarah. Bila tidak tercapai kesepakatan, perselisihan diselesaikan melalui {{YURISDIKSI}}.

22.3 Perselisihan antara Tenant dan Vendor atas transaksi diselesaikan terlebih dahulu melalui fitur Sengketa di PAGORA. Hal ini tidak menghilangkan hak para pihak untuk menempuh jalur hukum atau arbitrase di luar PAGORA.

## 23. Ketentuan Umum dan Kontak

23.1 Bila salah satu ketentuan S&K ini dinyatakan tidak sah atau tidak dapat dilaksanakan, ketentuan lainnya tetap berlaku.

23.2 Penyelenggara tidak melepaskan haknya bila tidak segera menegakkan suatu ketentuan.

23.3 Anda tidak boleh mengalihkan hak dan kewajiban Anda berdasarkan S&K ini tanpa persetujuan tertulis Penyelenggara.

23.4 Pertanyaan, keluhan, atau permintaan terkait S&K dan data pribadi dapat disampaikan ke {{EMAIL_KONTAK}}.

---

**Persetujuan:** Saya telah membaca dan menyetujui Syarat dan Ketentuan PAGORA.
