// Mirrors App\Enums\StatusPurchaseOrder. Keep both in sync.
export const STATUS_PO = {
    draft: { label: 'Draft', variant: 'secondary' },
    menunggu_tanggapan: { label: 'Menunggu Tanggapan', variant: 'sky' },
    negosiasi: { label: 'Negosiasi', variant: 'warning' },
    disepakati: { label: 'Disepakati', variant: 'green' },
    dalam_pengiriman: { label: 'Dalam Pengiriman', variant: 'sky' },
    diterima_gudang: { label: 'Diterima Gudang', variant: 'sky' },
    ditagih: { label: 'Ditagih', variant: 'warning' },
    lunas: { label: 'Lunas', variant: 'green' },
    dibatalkan: { label: 'Dibatalkan', variant: 'destructive' },
    sengketa: { label: 'Sengketa', variant: 'destructive' },
};

export const STATUS_FILTER_OPTIONS = [
    { value: 'all', label: 'Semua Status' },
    ...Object.entries(STATUS_PO).map(([value, { label }]) => ({ value, label })),
];

// Only drafts can be edited or deleted; agreement locks the PO (PRD §7).
export const isEditable = (po) => po?.status === 'draft';

export const isLocked = (po) =>
    !['draft', 'menunggu_tanggapan', 'negosiasi'].includes(po?.status);

export const hasNegotiation = (po) =>
    ['negosiasi', 'disepakati', 'dalam_pengiriman', 'diterima_gudang', 'ditagih', 'lunas', 'dibatalkan', 'sengketa'].includes(po?.status)
    && (po?.chat?.length ?? 0) > 0;

export const totalPo = (items = []) =>
    items.reduce((sum, item) => sum + Number(item.jumlah_pesanan || 0) * Number(item.harga_satuan_po || 0), 0);

// The latest offer in the chat; once the PO is `disepakati` this is the agreed price.
export const tawaranTerakhir = (po) =>
    [...(po?.chat ?? [])].reverse().find((c) => c.nominal_tawar !== null && c.nominal_tawar !== undefined) ?? null;

export const totalPagu = (items = []) =>
    items.reduce((sum, item) => sum + Number(item.jumlah_pesanan || 0) * Number(item.harga_pagu || 0), 0);
