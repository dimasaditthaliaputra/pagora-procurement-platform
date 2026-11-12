import {
    BadgeCheck,
    Camera,
    ClipboardCheck,
    EyeOff,
    FileCheck,
    Handshake,
    History,
    Lock,
    MessagesSquare,
    Receipt,
    Search,
    TrendingUp,
    Truck,
    Wallet,
} from 'lucide-react';

/** Commodities with reference prices from Bank Indonesia (matches KomoditasSeeder). */
export const commodities = [
    'Beras',
    'Gula Pasir',
    'Minyak Goreng',
    'Bawang Merah',
    'Bawang Putih',
    'Cabai Merah',
    'Cabai Rawit',
    'Telur Ayam',
    'Daging Ayam',
    'Daging Sapi',
];

export const features = [
    {
        id: 'harga-pagu',
        tab: 'Harga Pagu',
        icon: TrendingUp,
        role: 'Purchasing',
        title: 'Tahu batas harga wajar sebelum menawar',
        description:
            'Data harga pangan Bank Indonesia diolah dengan regresi linier menjadi rekomendasi Harga Pagu, batas harga wajar tertinggi per komoditas.',
        points: ['Tren harga per komoditas', 'Rekomendasi Harga Pagu otomatis', 'Acuan selama negosiasi berlangsung'],
        mockup: 'price',
    },
    {
        id: 'negosiasi',
        tab: 'Negosiasi',
        icon: MessagesSquare,
        role: 'Purchasing & Vendor',
        title: 'Tawar per item, lalu kunci kesepakatan',
        description:
            'Setiap item PO dinegosiasikan di ruang chat tertutup. Begitu semua item disepakati, harga dan dokumen terkunci dan chat menjadi hanya-baca.',
        points: ['Negosiasi per item', 'Vendor tidak melihat Harga Pagu', 'PO terkunci setelah sepakat'],
        mockup: 'negotiation',
    },
    {
        id: 'pengiriman',
        tab: 'Pengiriman',
        icon: Truck,
        role: 'Vendor',
        title: 'Vendor melaporkan pengirimannya sendiri',
        description:
            'Vendor mengisi form pengiriman langsung di portal. Status PO berubah menjadi Dalam Pengiriman dan kuantitas barang terkunci.',
        points: ['Form pengiriman mandiri', 'Kuantitas terkunci saat dikirim', 'Gudang tahu barang akan datang'],
        mockup: 'shipping',
    },
    {
        id: 'inspeksi',
        tab: 'Inspeksi QC',
        icon: ClipboardCheck,
        role: 'QC Gudang',
        title: 'Periksa per item, simpan buktinya',
        description:
            'QC Gudang menerima atau menolak setiap item dengan foto bukti. Item yang ditolak otomatis membuka sengketa untuk item tersebut.',
        points: ['Inspeksi per item', 'Foto bukti tersimpan', 'Sengketa terbuka otomatis'],
        mockup: 'inspection',
    },
    {
        id: 'matching',
        tab: '3-Way Matching',
        icon: FileCheck,
        role: 'Purchasing / Finance',
        title: 'Bayar hanya yang benar-benar diterima',
        description:
            'PO, penerimaan gudang, dan invoice dicocokkan otomatis. Pembayaran baru dibuka bila ketiganya cocok, dan dibekukan bila ada selisih.',
        points: ['PO ↔ Penerimaan ↔ Invoice', 'Pembayaran lewat payment gateway', 'Selisih langsung jadi sengketa'],
        mockup: 'matching',
    },
];

export const problems = [
    {
        id: 'harga',
        image: 'masalah-harga',
        label: 'Harga',
        color: 'bg-brand-orange',
        problem: 'Harga tidak transparan',
        detail: 'Negosiasi berjalan tanpa data pembanding, sehingga harga rawan di-mark-up.',
        solution: 'Harga Pagu dari data Bank Indonesia menjadi acuan sebelum dan selama negosiasi.',
    },
    {
        id: 'dokumen',
        image: 'masalah-dokumen',
        label: 'Dokumen',
        color: 'bg-brand-cyan',
        problem: 'Dokumen tercecer',
        detail: 'PO, surat jalan, dan tagihan tersebar di chat dan kertas, sehingga mudah salah catat.',
        solution: 'PO, chat, pengiriman, penerimaan, invoice, dan pembayaran tersimpan di satu portal.',
    },
    {
        id: 'pembayaran',
        image: 'masalah-pembayaran',
        label: 'Pembayaran',
        color: 'bg-brand-pink',
        problem: 'Bayar tanpa validasi',
        detail: 'Tagihan dibayar sebelum dicocokkan dengan barang yang benar-benar diterima gudang.',
        solution: '3-Way Matching mencocokkan PO, penerimaan, dan invoice sebelum pelunasan.',
    },
    {
        id: 'sengketa',
        image: 'masalah-sengketa',
        label: 'Sengketa',
        color: 'bg-brand-purple',
        problem: 'Sengketa tanpa bukti',
        detail: 'Barang ditolak, tetapi bukti dan riwayat percakapannya tidak tercatat di satu tempat.',
        solution: 'Log Sengketa permanen berisi riwayat chat dan foto bukti inspeksi QC.',
    },
];

export const workflowSteps = [
    {
        title: 'Kemitraan',
        image: 'kemitraan',
        role: 'Manajemen & Vendor',
        icon: Handshake,
        color: 'bg-brand-cyan',
        description: 'Perusahaan dan vendor menjalin kerja sama sampai disepakati. Ini syarat sebelum PO dibuat.',
    },
    {
        title: 'Riset harga',
        image: 'riset-harga',
        role: 'Purchasing',
        icon: Search,
        color: 'bg-brand-lime',
        description: 'Purchasing membuka dasbor analitik dan melihat tren harga serta rekomendasi Harga Pagu.',
    },
    {
        title: 'Draf PO',
        image: 'draf-po',
        role: 'Purchasing',
        icon: Receipt,
        color: 'bg-brand-yellow',
        description: 'PO multi-item disusun lengkap dengan estimasi harga. Draf masih bisa diubah atau dihapus.',
    },
    {
        title: 'Negosiasi',
        image: 'negosiasi',
        role: 'Purchasing & Vendor',
        icon: MessagesSquare,
        color: 'bg-brand-pink',
        description: 'Setiap item ditawar sampai disepakati atau dikeluarkan. Setelah sepakat, PO terkunci.',
    },
    {
        title: 'Pengiriman',
        image: 'pengiriman',
        role: 'Vendor',
        icon: Truck,
        color: 'bg-brand-orange',
        description: 'Vendor mengisi form pengiriman. Status menjadi Dalam Pengiriman dan kuantitas terkunci.',
    },
    {
        title: 'Inspeksi QC',
        image: 'inspeksi',
        role: 'QC Gudang',
        icon: Camera,
        color: 'bg-brand-purple',
        description: 'Setiap item diperiksa. Item yang ditolak disertai foto bukti dan membuka sengketa.',
    },
    {
        title: 'Invoice & matching',
        image: 'invoice',
        role: 'Vendor & Purchasing',
        icon: FileCheck,
        color: 'bg-brand-cyan',
        description: 'Vendor menagih item yang lolos QC, lalu sistem mencocokkan PO, penerimaan, dan invoice.',
    },
    {
        title: 'Pelunasan',
        image: 'pelunasan',
        role: 'Purchasing / Finance',
        icon: Wallet,
        color: 'bg-brand-lime',
        description: 'Tagihan yang cocok dibayar lewat payment gateway. PO selesai saat semua item beres.',
    },
];

export const roleCards = [
    {
        eyebrow: 'Owner & Manajemen',
        title: 'Kendali penuh tanpa repot',
        description:
            'Kelola perusahaan, anggota tim, komoditas aktif, dan kerja sama vendor. Tinjau sengketa dan tetapkan penyelesaiannya.',
        link: { label: 'Daftarkan perusahaan', href: '/register' },
        color: 'bg-brand-purple',
        image: 'peran-owner',
    },
    {
        eyebrow: 'Purchasing / Finance',
        title: 'Belanja dengan acuan, bayar dengan bukti',
        description:
            'Pantau Harga Pagu, susun PO, negosiasikan per item, lalu verifikasi tagihan dengan 3-Way Matching sebelum membayar.',
        link: { label: 'Lihat fitur Harga Pagu', href: '#fitur' },
        color: 'bg-brand-lime',
        image: 'peran-purchasing',
    },
    {
        eyebrow: 'QC Gudang',
        title: 'Terima barang dengan catatan yang jelas',
        description:
            'Periksa setiap item yang datang, terima atau tolak dengan foto bukti. Tidak ada lagi catatan penerimaan yang hilang.',
        link: { label: 'Lihat alur inspeksi', href: '#alur' },
        color: 'bg-brand-orange',
        image: 'peran-qc',
    },
    {
        eyebrow: 'Vendor',
        title: 'Tawar, kirim, tagih. Selesai.',
        description:
            'Tanggapi PO, negosiasikan harga per item, laporkan pengiriman, dan terbitkan invoice untuk item yang lolos QC.',
        link: { label: 'Daftar sebagai vendor', href: '/register/vendor' },
        color: 'bg-brand-cyan',
        image: 'peran-vendor',
    },
];

export const safeguards = [
    {
        icon: Lock,
        image: 'aman-isolasi',
        color: 'bg-brand-lime',
        title: 'Data antar-perusahaan terisolasi',
        description: 'Setiap perusahaan hanya melihat datanya sendiri. Vendor tidak bisa membuka halaman internal perusahaan.',
    },
    {
        icon: EyeOff,
        image: 'aman-harga-pagu',
        color: 'bg-brand-pink',
        title: 'Harga Pagu tidak terlihat vendor',
        description: 'Acuan harga internal tidak pernah dikirim ke halaman vendor, sehingga posisi tawar tetap aman.',
    },
    {
        icon: History,
        image: 'aman-riwayat',
        color: 'bg-brand-cyan',
        title: 'Riwayat tidak bisa dihapus',
        description: 'Data transaksi tidak dihapus permanen. Log Sengketa menyimpan chat dan foto bukti untuk audit.',
    },
    {
        icon: BadgeCheck,
        image: 'aman-verifikasi',
        color: 'bg-brand-orange',
        title: 'Akun diverifikasi',
        description: 'Perusahaan dan legalitas vendor diverifikasi Superadmin sebelum bisa bertransaksi.',
    },
];

/** PO status labels, as defined in PRD §9.1. */
export const poStatuses = [
    'Draft',
    'Negosiasi',
    'Disepakati',
    'Dalam Pengiriman',
    'Diterima Sebagian',
    'Diterima Lengkap',
    'Ditagih',
    'Siap Dibayar',
    'Lunas',
    'Selesai',
];

export const navigation = [
    {
        label: 'Produk',
        items: features.map((feature) => ({
            label: feature.tab,
            description: feature.title,
            role: feature.role,
            href: `#fitur-${feature.id}`,
            icon: feature.icon,
        })),
    },
    { label: 'Alur Kerja', href: '#alur' },
    { label: 'Peran', href: '#peran' },
    { label: 'Keamanan', href: '#keamanan' },
];
