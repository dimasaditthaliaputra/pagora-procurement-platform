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
            'roles',
            'tenants',
            'vendors',
            'users',
            'vendor_legal_documents',
            'vendor_invitations',
            'settings',
            'kerja_sama',
            'commodity_categories',
            'commodities',
            'tenant_commodities',
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
        $this->assertTrue(Schema::hasColumns('roles', [
            'id',
            'name',
            'description',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('tenants', [
            'id',
            'uuid',
            'company_name',
            'tax_number',
            'legal_document_path',
            'address',
            'verification_status',
            'verification_note',
            'verified_by',
            'verified_at',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $this->assertFalse(Schema::hasColumn('tenants', 'tipe_paket'));

        $this->assertTrue(Schema::hasColumns('kerja_sama', [
            'id',
            'uuid',
            'id_perusahaan',
            'id_vendor',
            'status_kerjasama',
            'catatan_penolakan',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('vendors', [
            'id',
            'uuid',
            'slug',
            'company_name',
            'tax_number',
            'contact_email',
            'phone',
            'address',
            'kategori_komoditas',
            'verification_status',
            'is_active',
            'block_reason',
            'blocked_by',
            'blocked_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $this->assertFalse(Schema::hasColumn('vendors', 'dokumen_legalitas'));

        $this->assertTrue(Schema::hasColumns('users', [
            'id',
            'uuid',
            'name',
            'email',
            'password',
            'google_id',
            'avatar',
            'role_id',
            'tenant_id',
            'vendor_id',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('vendor_legal_documents', [
            'id',
            'uuid',
            'vendor_id',
            'document_type',
            'document_number',
            'file_path',
            'verification_status',
            'verified_by',
            'verified_at',
            'rejection_reason',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('vendor_invitations', [
            'id',
            'uuid',
            'company_name',
            'email',
            'kategori_komoditas',
            'message',
            'token',
            'status',
            'invited_by',
            'tenant_id',
            'expires_at',
            'registered_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('settings', [
            'id',
            'group',
            'key',
            'value',
            'is_encrypted',
            'updated_by',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('commodity_categories', [
            'id',
            'uuid',
            'name',
            'description',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('commodities', [
            'id',
            'uuid',
            'sku',
            'slug',
            'name',
            'commodity_category_id',
            'unit',
            'default_shrinkage_tolerance',
            'api_reference_code',
            'specification',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $this->assertFalse(Schema::hasColumn('commodities', 'kategori'));

        $this->assertTrue(Schema::hasColumns('tenant_commodities', [
            'id',
            'tenant_id',
            'commodity_id',
            'is_active',
            'shrinkage_tolerance',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('harga_referensi_komoditas', [
            'id',
            'uuid',
            'komoditas_id',
            'tanggal',
            'harga',
            'sumber_data',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('prediksi_harga', [
            'id',
            'uuid',
            'komoditas_id',
            'tanggal_prediksi',
            'harga_prediksi',
            'harga_pagu_rekomendasi',
            'dihitung_pada',
        ]));

        $this->assertTrue(Schema::hasColumns('purchase_order', [
            'id',
            'uuid',
            'nomor_po',
            'vendor_id',
            'created_by',
            'prediksi_harga_id',
            'perusahaan_buyer_id',
            'harga_pagu_acuan',
            'status',
            'tanggal_po',
            'catatan',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('po_item', [
            'id',
            'uuid',
            'purchase_order_id',
            'komoditas_id',
            'jumlah_pesanan',
            'harga_satuan_po',
            'subtotal',
            'harga_tawar_vendor',
            'status_item',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('chat_negosiasi', [
            'id',
            'uuid',
            'purchase_order_id',
            'pengirim_id',
            'pesan_teks',
            'nominal_tawar',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('pengiriman', [
            'id',
            'uuid',
            'purchase_order_id',
            'jalur_pengiriman',
            'nama_armada_ekspedisi',
            'no_polisi_armada',
            'perkiraan_tanggal_tiba',
            'status',
            'dilaporkan_oleh',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('penerimaan_barang', [
            'id',
            'uuid',
            'purchase_order_id',
            'check_by',
            'no_polisi_dicocokkan',
            'tanggal_pemeriksaan',
            'catatan',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('penerimaan_barang_item', [
            'id',
            'uuid',
            'penerimaan_barang_id',
            'po_item_id',
            'jumlah_diterima',
            'status',
            'catatan',
            'foto_bukti_penolakan',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('invoice', [
            'id',
            'uuid',
            'purchase_order_id',
            'nomor_invoice_vendor',
            'jumlah_tagihan',
            'file_invoice',
            'tanggal_invoice',
            'status_matching',
            'diverifikasi_oleh',
            'diverifikasi_pada',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('sengketa', [
            'id',
            'uuid',
            'invoice_id',
            'dibuka_oleh',
            'status',
            'tanggal_dibuka',
            'tanggal_selesai',
            'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('log_sengketa', [
            'id',
            'uuid',
            'sengketa_id',
            'user_id',
            'isi_pesan',
            'file_bukti',
            'created_at',
        ]));
    }
}
