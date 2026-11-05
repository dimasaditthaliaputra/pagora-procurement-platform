<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Dispute trigger types (`disputes.trigger_type` — PRD §9.3, design-db.md §4.24 & §6).
 */
enum PemicuSengketa: string
{
    case GoodsMismatch = 'goods_mismatch';
    case QcRejected = 'qc_rejected';
    case QuantityMismatch = 'quantity_mismatch';
    case AmountMismatch = 'amount_mismatch';
    case SettlementDisagreement = 'settlement_disagreement';

    public function label(): string
    {
        return match ($this) {
            self::GoodsMismatch => 'Barang Rusak / Tidak Sesuai Pesanan',
            self::QcRejected => 'Hasil QC Ditolak (Mutu Tidak Sesuai)',
            self::QuantityMismatch => 'Jumlah Barang Tidak Sesuai Invoice',
            self::AmountMismatch => 'Nilai Tagihan Tidak Sesuai Kesepakatan',
            self::SettlementDisagreement => 'Ketidaksepakatan Penyelesaian Transaksi',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GoodsMismatch => 'Kemasan pecah/basah, kondisi fisik barang rusak/cacat, jenis barang keliru, atau bobot fisik menyusut melebihi batas toleransi.',
            self::QcRejected => 'Spesifikasi mutu atau kualitas bahan baku (kadar air, ukuran, kesegaran, grade) tidak memenuhi standar penerimaan QC Gudang.',
            self::QuantityMismatch => 'Kuantitas pada tagihan (invoice) melebihi kuantitas barang yang diterima di gudang pada 3-Way Matching.',
            self::AmountMismatch => 'Harga satuan atau nilai total pada tagihan (invoice) tidak sesuai dengan harga kesepakatan Purchase Order.',
            self::SettlementDisagreement => 'Vendor dan perusahaan pembeli belum menyepakati penyelesaian atas kendala transaksi pengadaan.',
        };
    }

    /**
     * Triggers applicable during QC Gudang inspection & damage reporting (`UC-15 «extend»`).
     *
     * @return list<self>
     */
    public static function qcInspectionCases(): array
    {
        return [
            self::GoodsMismatch,
            self::QcRejected,
        ];
    }

    /**
     * String values allowed for QC Gudang inspection damage/rejection reporting.
     *
     * @return list<string>
     */
    public static function qcInspectionValues(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::qcInspectionCases()
        );
    }

    /**
     * Formatted options for frontend Select / radio controls.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function qcInspectionOptions(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            self::qcInspectionCases()
        );
    }
}
