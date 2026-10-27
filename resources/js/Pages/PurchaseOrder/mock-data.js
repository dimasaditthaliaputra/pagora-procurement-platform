/**
 * FRONTEND PROTOTYPE ONLY.
 *
 * Field names follow the real migrations (purchase_order, po_item, chat_negosiasi) so pages can
 * switch to Inertia props without reshaping. Replace every import of this file with controller
 * props once PurchaseOrderService exists. The store lives in module memory, so changes survive
 * Inertia navigation but reset on a full page reload.
 */

export const CURRENT_BUYER = { nama: 'Staff Buyer', peran: 'buyer' };

export const vendorOptions = [
    { value: '6f1c2a9e-1b7d-4c3e-9a51-0d2e8f4b7a10', label: 'CV Mitra Agro Sejahtera' },
    { value: '8a3d5b1f-2c4e-4f6a-8b7c-1e9d0a2b3c41', label: 'PT Sumber Pangan Utama' },
    { value: '2b7e9c4d-5f1a-4e3b-9c8d-7a6b5c4d3e52', label: 'UD Berkah Tani Mandiri' },
];

// harga_pagu represents the latest prediksi_harga recommendation for the commodity.
export const komoditasOptions = [
    { value: 'c1a1b2c3-0001-4a00-8000-000000000001', label: 'Beras', satuan: 'kg', harga_pagu: 13500 },
    { value: 'c1a1b2c3-0002-4a00-8000-000000000002', label: 'Bawang Merah', satuan: 'kg', harga_pagu: 38000 },
    { value: 'c1a1b2c3-0003-4a00-8000-000000000003', label: 'Bawang Putih', satuan: 'kg', harga_pagu: 36500 },
    { value: 'c1a1b2c3-0004-4a00-8000-000000000004', label: 'Cabai Merah', satuan: 'kg', harga_pagu: 52000 },
    { value: 'c1a1b2c3-0005-4a00-8000-000000000005', label: 'Cabai Rawit', satuan: 'kg', harga_pagu: 61000 },
    { value: 'c1a1b2c3-0006-4a00-8000-000000000006', label: 'Daging Ayam', satuan: 'kg', harga_pagu: 37500 },
    { value: 'c1a1b2c3-0007-4a00-8000-000000000007', label: 'Daging Sapi', satuan: 'kg', harga_pagu: 134000 },
    { value: 'c1a1b2c3-0008-4a00-8000-000000000008', label: 'Telur Ayam', satuan: 'kg', harga_pagu: 29500 },
    { value: 'c1a1b2c3-0009-4a00-8000-000000000009', label: 'Gula Pasir', satuan: 'kg', harga_pagu: 17800 },
    { value: 'c1a1b2c3-0010-4a00-8000-000000000010', label: 'Minyak Goreng', satuan: 'liter', harga_pagu: 17200 },
];

const komoditas = (uuid) => komoditasOptions.find((k) => k.value === uuid);
const vendor = (uuid) => vendorOptions.find((v) => v.value === uuid);

function item(uuid, komoditasUuid, jumlah, harga, hargaTawar = null) {
    const k = komoditas(komoditasUuid);
    return {
        uuid,
        komoditas_uuid: komoditasUuid,
        nama_komoditas: k.label,
        satuan: k.satuan,
        harga_pagu: k.harga_pagu,
        jumlah_pesanan: jumlah,
        harga_satuan_po: harga,
        harga_tawar_vendor: hargaTawar,
    };
}

function po({ uuid, nomor, vendorUuid, status, tanggal, catatan = '', items, chat = [] }) {
    const v = vendor(vendorUuid);
    return {
        uuid,
        nomor_po: nomor,
        vendor: { uuid: v.value, nama_perusahaan: v.label },
        dibuat_oleh: CURRENT_BUYER.nama,
        status,
        tanggal_po: tanggal,
        catatan,
        items,
        chat,
        created_at: `${tanggal}T08:00:00`,
    };
}

const VENDOR_CONTACT = (nama) => ({ nama, peran: 'vendor' });

export const vendorContact = (purchaseOrder) => VENDOR_CONTACT(`Perwakilan ${purchaseOrder.vendor.nama_perusahaan}`);

let store = [
    po({
        uuid: '0b9a1c2d-3e4f-4a5b-8c6d-7e8f9a0b1c01',
        nomor: 'PO-2026-0007',
        vendorUuid: '6f1c2a9e-1b7d-4c3e-9a51-0d2e8f4b7a10',
        status: 'draft',
        tanggal: '2026-10-26',
        catatan: 'Kebutuhan produksi minggu ke-1 November.',
        items: [
            item('a0000001-0000-4000-8000-000000000001', 'c1a1b2c3-0001-4a00-8000-000000000001', 1500, 13200),
            item('a0000001-0000-4000-8000-000000000002', 'c1a1b2c3-0009-4a00-8000-000000000009', 400, 17500),
        ],
    }),
    po({
        uuid: '0b9a1c2d-3e4f-4a5b-8c6d-7e8f9a0b1c02',
        nomor: 'PO-2026-0006',
        vendorUuid: '2b7e9c4d-5f1a-4e3b-9c8d-7a6b5c4d3e52',
        status: 'draft',
        tanggal: '2026-10-25',
        items: [
            item('a0000002-0000-4000-8000-000000000001', 'c1a1b2c3-0010-4a00-8000-000000000010', 800, 16900),
        ],
    }),
    po({
        uuid: '0b9a1c2d-3e4f-4a5b-8c6d-7e8f9a0b1c03',
        nomor: 'PO-2026-0005',
        vendorUuid: '8a3d5b1f-2c4e-4f6a-8b7c-1e9d0a2b3c41',
        status: 'negosiasi',
        tanggal: '2026-10-24',
        catatan: 'Prioritas pengiriman sebelum 5 November.',
        items: [
            item('a0000003-0000-4000-8000-000000000001', 'c1a1b2c3-0002-4a00-8000-000000000002', 250, 37000),
            item('a0000003-0000-4000-8000-000000000002', 'c1a1b2c3-0004-4a00-8000-000000000004', 120, 50000),
        ],
        chat: [
            {
                uuid: 'b0000003-0000-4000-8000-000000000001',
                pengirim: CURRENT_BUYER,
                pesan_teks: 'Kami ajukan harga sesuai rekomendasi Harga Pagu dikurangi 3%.',
                nominal_tawar: 15250000,
                created_at: '2026-10-24T09:15:00',
            },
            {
                uuid: 'b0000003-0000-4000-8000-000000000002',
                pengirim: VENDOR_CONTACT('Rudi (PT Sumber Pangan Utama)'),
                pesan_teks: 'Harga cabai sedang naik di tingkat petani. Kami bisa di angka berikut.',
                nominal_tawar: 15900000,
                created_at: '2026-10-24T13:40:00',
            },
        ],
    }),
    po({
        uuid: '0b9a1c2d-3e4f-4a5b-8c6d-7e8f9a0b1c04',
        nomor: 'PO-2026-0004',
        vendorUuid: '6f1c2a9e-1b7d-4c3e-9a51-0d2e8f4b7a10',
        status: 'disepakati',
        tanggal: '2026-10-20',
        items: [
            item('a0000004-0000-4000-8000-000000000001', 'c1a1b2c3-0006-4a00-8000-000000000006', 300, 36800),
            item('a0000004-0000-4000-8000-000000000002', 'c1a1b2c3-0008-4a00-8000-000000000008', 200, 29000),
        ],
        chat: [
            {
                uuid: 'b0000004-0000-4000-8000-000000000001',
                pengirim: CURRENT_BUYER,
                pesan_teks: 'Penawaran awal untuk ayam dan telur.',
                nominal_tawar: 16600000,
                created_at: '2026-10-20T10:00:00',
            },
            {
                uuid: 'b0000004-0000-4000-8000-000000000002',
                pengirim: VENDOR_CONTACT('Sari (CV Mitra Agro Sejahtera)'),
                pesan_teks: 'Harga disetujui.',
                nominal_tawar: 16840000,
                created_at: '2026-10-20T11:30:00',
            },
            {
                uuid: 'b0000004-0000-4000-8000-000000000003',
                pengirim: CURRENT_BUYER,
                pesan_teks: 'Setuju dengan harga yang diajukan vendor.',
                nominal_tawar: 16840000,
                created_at: '2026-10-20T12:05:00',
            },
        ],
    }),
    po({
        uuid: '0b9a1c2d-3e4f-4a5b-8c6d-7e8f9a0b1c05',
        nomor: 'PO-2026-0003',
        vendorUuid: '2b7e9c4d-5f1a-4e3b-9c8d-7a6b5c4d3e52',
        status: 'dibatalkan',
        tanggal: '2026-10-15',
        items: [
            item('a0000005-0000-4000-8000-000000000001', 'c1a1b2c3-0007-4a00-8000-000000000007', 100, 125000),
        ],
        chat: [
            {
                uuid: 'b0000005-0000-4000-8000-000000000001',
                pengirim: CURRENT_BUYER,
                pesan_teks: 'Penawaran daging sapi.',
                nominal_tawar: 12500000,
                created_at: '2026-10-15T09:00:00',
            },
            {
                uuid: 'b0000005-0000-4000-8000-000000000002',
                pengirim: VENDOR_CONTACT('Pak Darto (UD Berkah Tani Mandiri)'),
                pesan_teks: 'Belum bisa, stok terbatas.',
                nominal_tawar: 15200000,
                created_at: '2026-10-15T14:20:00',
            },
        ],
    }),
];

export function getPurchaseOrders() {
    return store;
}

export function findPurchaseOrder(uuid) {
    return store.find((p) => p.uuid === uuid) ?? null;
}

export function savePurchaseOrder(payload, uuid = null) {
    const v = vendor(payload.vendor_uuid);
    const items = payload.items.map((row) => ({
        ...item(row.uuid ?? crypto.randomUUID(), row.komoditas_uuid, Number(row.jumlah_pesanan), Number(row.harga_satuan_po)),
    }));

    if (uuid) {
        store = store.map((p) => (p.uuid === uuid
            ? { ...p, vendor: { uuid: v.value, nama_perusahaan: v.label }, tanggal_po: payload.tanggal_po, catatan: payload.catatan, items }
            : p));
        return findPurchaseOrder(uuid);
    }

    const nomor = `PO-2026-${String(store.length + 1).padStart(4, '0')}`;
    const created = po({
        uuid: crypto.randomUUID(),
        nomor,
        vendorUuid: payload.vendor_uuid,
        status: 'draft',
        tanggal: payload.tanggal_po,
        catatan: payload.catatan,
        items: [],
    });
    created.items = items;
    store = [created, ...store];
    return created;
}

export function updatePurchaseOrder(uuid, changes) {
    store = store.map((p) => (p.uuid === uuid ? { ...p, ...changes } : p));
    return findPurchaseOrder(uuid);
}

export function deletePurchaseOrder(uuid) {
    store = store.filter((p) => p.uuid !== uuid);
}

export function addChat(uuid, message) {
    const current = findPurchaseOrder(uuid);
    const entry = { uuid: crypto.randomUUID(), created_at: new Date().toISOString(), ...message };
    return updatePurchaseOrder(uuid, { chat: [...(current?.chat ?? []), entry] });
}

/** Simulates server latency so loading states can be reviewed. */
export function simulateRequest(ms = 600) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
