<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that all tables defined in design-db.md are created.
     */
    public function test_all_design_db_tables_exist(): void
    {
        $tables = [
            'role',
            'vendor',
            'users',
            'dokumen_legalitas_vendor',
            'komoditas',
            'harga_referensi_komoditas',
            'prediksi_harga',
            'purchase_order',
            'po_item',
            'chat_negosiasi',
            'pengiriman',
            'penerimaan_barang',
            'penerimaan_barang_item',
            'invoice',
            'sengketa',
            'log_sengketa',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} does not exist in schema.");
        }
    }

    /**
     * Test key columns on user and transaction tables.
     */
    public function test_table_columns_match_design(): void
    {
        $this->assertTrue(Schema::hasColumns('role', [
            'id', 'nama_role', 'deskripsi', 'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('vendor', [
            'id', 'uuid', 'slug', 'nama_perusahaan', 'email_kontak', 'no_telepon', 'alamat',
            'status_verifikasi', 'status_aktif', 'alasan_pemblokiran', 'blocked_by',
            'diblokir_pada', 'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'uuid', 'nama_lengkap', 'email', 'password', 'role_id', 'vendor_id',
            'status_aktif', 'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('dokumen_legalitas_vendor', [
            'id', 'uuid', 'vendor_id', 'jenis_dokumen', 'nomor_dokumen', 'file_url',
            'status_verifikasi', 'verif_by', 'diverifikasi_pada', 'created_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('komoditas', [
            'id', 'uuid', 'slug', 'nama_komoditas', 'satuan', 'kode_referensi_api',
            'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('harga_referensi_komoditas', [
            'id', 'uuid', 'komoditas_id', 'tanggal', 'harga', 'sumber_data', 'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('prediksi_harga', [
            'id', 'uuid', 'komoditas_id', 'tanggal_prediksi', 'harga_prediksi',
            'harga_pagu_rekomendasi', 'dihitung_pada',
        ]));

        $this->assertTrue(Schema::hasColumns('purchase_order', [
            'id', 'uuid', 'nomor_po', 'vendor_id', 'created_by', 'prediksi_harga_id',
            'harga_pagu_acuan', 'status', 'tanggal_po', 'catatan',
            'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('po_item', [
            'id', 'uuid', 'purchase_order_id', 'komoditas_id', 'jumlah_pesanan',
            'harga_satuan_po', 'subtotal', 'harga_tawar_vendor', 'status_item',
            'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('chat_negosiasi', [
            'id', 'uuid', 'purchase_order_id', 'pengirim_id', 'pesan_teks', 'nominal_tawar', 'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('pengiriman', [
            'id', 'uuid', 'purchase_order_id', 'jalur_pengiriman', 'nama_armada_ekspedisi',
            'no_polisi_armada', 'perkiraan_tanggal_tiba', 'status', 'dilaporkan_oleh',
            'created_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('penerimaan_barang', [
            'id', 'uuid', 'purchase_order_id', 'check_by', 'no_polisi_dicocokkan',
            'tanggal_pemeriksaan', 'catatan', 'created_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('penerimaan_barang_item', [
            'id', 'uuid', 'penerimaan_barang_id', 'po_item_id', 'jumlah_diterima',
            'status', 'catatan', 'foto_bukti_penolakan', 'created_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('invoice', [
            'id', 'uuid', 'purchase_order_id', 'nomor_invoice_vendor', 'jumlah_tagihan',
            'file_invoice', 'tanggal_invoice', 'status_matching', 'diverifikasi_oleh',
            'diverifikasi_pada', 'created_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('sengketa', [
            'id', 'uuid', 'invoice_id', 'dibuka_oleh', 'status', 'tanggal_dibuka',
            'tanggal_selesai', 'created_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('log_sengketa', [
            'id', 'uuid', 'sengketa_id', 'user_id', 'isi_pesan', 'file_bukti', 'created_at',
        ]));
    }
}
