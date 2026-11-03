<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $buyer = User::where('email', 'buyer@pagora.test')->first();
        $owner = User::where('email', 'owner@pagora.test')->first();
        $vendor1 = User::where('email', 'vendor1@pagora.test')->first();

        $samplePo = PurchaseOrder::first();
        $poUrl = $samplePo ? "/purchase-order/{$samplePo->uuid}/negosiasi" : '/purchase-order';
        $poNomor = $samplePo ? $samplePo->nomor_po : 'PO-202611-0001';

        // 1. Staff Purchasing: Fokus pada aktivitas PO, Negosiasi Harga, dan Pengingat Sistem
        if ($buyer) {
            Notification::firstOrCreate(
                ['user_id' => $buyer->id, 'title' => "Tawaran Baru PO {$poNomor}"],
                [
                    'type' => 'negotiation',
                    'message' => "Vendor Mitra Agro mengajukan tawaran harga baru untuk {$poNomor}.",
                    'action_url' => $poUrl,
                    'read_at' => null,
                    'created_at' => Carbon::now()->subMinutes(15),
                ]
            );

            Notification::firstOrCreate(
                ['user_id' => $buyer->id, 'title' => "Draf PO {$poNomor} Berhasil Disimpan"],
                [
                    'type' => 'purchase_order',
                    'message' => "Purchase Order {$poNomor} tersimpan sebagai draf dan siap diajukan.",
                    'action_url' => $samplePo ? "/purchase-order/{$samplePo->uuid}" : '/purchase-order',
                    'read_at' => Carbon::now()->subHours(2),
                    'created_at' => Carbon::now()->subHours(2),
                ]
            );

            Notification::firstOrCreate(
                ['user_id' => $buyer->id, 'title' => 'Pengingat Batas Toleransi Negosiasi'],
                [
                    'type' => 'system',
                    'message' => 'Pastikan harga kesepakatan tidak melebihi Harga Pagu acuan BI.',
                    'action_url' => null, // Reminder only: matikan redirect
                    'read_at' => null,
                    'created_at' => Carbon::now()->subHours(4),
                ]
            );
        }

        // 2. Owner Tenant: Akses penuh termasuk Kemitraan (Kerja Sama), Pegawai, dan PO
        if ($owner) {
            Notification::firstOrCreate(
                ['user_id' => $owner->id, 'title' => 'Pengajuan Kerja Sama Diterima'],
                [
                    'type' => 'partnership',
                    'message' => 'Vendor Berkah Tani menyetujui ajakan kerja sama kemitraan baru.',
                    'action_url' => '/master/vendor',
                    'read_at' => null,
                    'created_at' => Carbon::now()->subMinutes(25),
                ]
            );

            Notification::firstOrCreate(
                ['user_id' => $owner->id, 'title' => "Pengajuan PO Menunggu Respons: {$poNomor}"],
                [
                    'type' => 'purchase_order',
                    'message' => "PO {$poNomor} sedang dalam tahap negosiasi harga dengan vendor.",
                    'action_url' => $poUrl,
                    'read_at' => null,
                    'created_at' => Carbon::now()->subHours(1),
                ]
            );

            Notification::firstOrCreate(
                ['user_id' => $owner->id, 'title' => 'Pegawai Baru Ditambahkan'],
                [
                    'type' => 'employee',
                    'message' => 'Akun pegawai untuk staf purchasing baru telah berhasil dibuat dan aktif.',
                    'action_url' => '/manajemen/pengguna',
                    'read_at' => Carbon::now()->subHours(5),
                    'created_at' => Carbon::now()->subHours(5),
                ]
            );
        }

        // 3. Vendor: Fokus pada PO Masuk, Negosiasi, Kemitraan, dan Dokumen Legalitas
        if ($vendor1) {
            Notification::firstOrCreate(
                ['user_id' => $vendor1->id, 'title' => "PO Baru Masuk: {$poNomor}"],
                [
                    'type' => 'purchase_order',
                    'message' => "PT Pagora Nusantara mengirimkan Purchase Order {$poNomor} untuk ditinjau.",
                    'action_url' => $poUrl,
                    'read_at' => null,
                    'created_at' => Carbon::now()->subMinutes(10),
                ]
            );

            Notification::firstOrCreate(
                ['user_id' => $vendor1->id, 'title' => 'Ajakan Kemitraan dari PT Pagora Nusantara'],
                [
                    'type' => 'partnership',
                    'message' => 'Perusahaan pembeli ingin menjalin kerja sama pengadaan komoditas.',
                    'action_url' => '/kerja-sama/calon-mitra',
                    'read_at' => null,
                    'created_at' => Carbon::now()->subHours(3),
                ]
            );

            Notification::firstOrCreate(
                ['user_id' => $vendor1->id, 'title' => 'Dokumen Legalitas Terverifikasi'],
                [
                    'type' => 'verification',
                    'message' => 'Seluruh dokumen NPWP dan NIB perusahaan Anda telah disetujui Superadmin.',
                    'action_url' => null, // Reminder only
                    'read_at' => Carbon::now()->subDays(2),
                    'created_at' => Carbon::now()->subDays(2),
                ]
            );
        }
    }
}
