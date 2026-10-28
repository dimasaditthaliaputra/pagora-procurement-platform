import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    Building2,
    CheckCircle2,
    Clock,
    FileText,
    Mail,
    MapPin,
    Phone,
    ShieldAlert,
    ShieldCheck,
    Tag,
    UserCheck,
    XCircle,
} from 'lucide-react';

export function VendorDetailDialog({ vendor, open, onOpenChange, onBlock, onUnblock, canManage }) {
    if (!vendor) return null;

    const isBlocked = !vendor.status_aktif;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Building2 className="size-5 text-emerald-600" />
                        Detail Informasi Rekanan Vendor
                    </DialogTitle>
                    <DialogDescription>
                        Profil lengkap rekanan pengadaan barang dan komoditas.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2 text-sm">
                    {/* Header Box */}
                    <div className="rounded-lg border bg-muted/40 p-4 space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 className="font-semibold text-lg text-foreground">
                                    {vendor.nama_perusahaan}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Slug: <code className="bg-muted px-1.5 py-0.5 rounded text-xs">{vendor.slug}</code>
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                {isBlocked ? (
                                    <Badge variant="destructive" className="flex items-center gap-1 font-semibold">
                                        <ShieldAlert className="size-3.5" />
                                        DIBLOKIR
                                    </Badge>
                                ) : (
                                    <Badge className="bg-emerald-500 hover:bg-emerald-600 text-white flex items-center gap-1">
                                        <CheckCircle2 className="size-3.5" />
                                        Aktif
                                    </Badge>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs border-t pt-3">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Mail className="size-4 shrink-0 text-foreground" />
                                <span className="text-foreground font-medium">{vendor.email_kontak || '-'}</span>
                            </div>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <Phone className="size-4 shrink-0 text-foreground" />
                                <span className="text-foreground font-medium">{vendor.no_telepon || '-'}</span>
                            </div>
                            <div className="flex items-center gap-2 text-muted-foreground sm:col-span-2">
                                <MapPin className="size-4 shrink-0 text-foreground" />
                                <span className="text-foreground">{vendor.alamat || 'Alamat belum dilengkapi'}</span>
                            </div>
                        </div>
                    </div>

                    {/* Kategori & Status Verifikasi */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="rounded-md border p-3 bg-card">
                            <span className="text-xs text-muted-foreground flex items-center gap-1.5 mb-1.5">
                                <Tag className="size-3.5" /> Kategori Komoditas
                            </span>
                            <p className="font-medium text-foreground text-sm">
                                {vendor.kategori_komoditas || 'Umum'}
                            </p>
                        </div>
                        <div className="rounded-md border p-3 bg-card">
                            <span className="text-xs text-muted-foreground flex items-center gap-1.5 mb-1.5">
                                <FileText className="size-3.5" /> Verifikasi Dokumen Legalitas
                            </span>
                            <div>
                                {vendor.status_verifikasi === 'terverifikasi' ? (
                                    <Badge className="bg-emerald-600 text-white">Terverifikasi</Badge>
                                ) : vendor.status_verifikasi === 'ditolak' ? (
                                    <Badge variant="destructive">Ditolak</Badge>
                                ) : (
                                    <Badge variant="outline" className="text-amber-600 border-amber-300 bg-amber-50 dark:bg-amber-950/40">
                                        Pending Review
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Catatan Pemblokiran jika Vendor Diblokir */}
                    {isBlocked && (
                        <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-4 space-y-2">
                            <div className="flex items-center gap-2 text-destructive font-semibold text-xs">
                                <ShieldAlert className="size-4" />
                                <span>Informasi Pemblokiran Vendor</span>
                            </div>
                            <p className="text-sm font-medium text-foreground bg-background/60 p-2.5 rounded border border-destructive/20 italic">
                                "{vendor.alasan_pemblokiran || 'Tidak ada catatan alasan pemblokiran.'}"
                            </p>
                            <div className="flex flex-wrap items-center justify-between text-xs text-muted-foreground pt-1">
                                <span>
                                    Eksekutor: <strong className="text-foreground">{vendor.blocked_by_user?.nama_lengkap ?? 'Manajemen'}</strong>
                                </span>
                                <span>
                                    Waktu: {vendor.diblokir_pada ? new Date(vendor.diblokir_pada).toLocaleString('id-ID') : '-'}
                                </span>
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter className="flex flex-col-reverse sm:flex-row sm:justify-between sm:space-x-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Tutup
                    </Button>

                    {canManage && (
                        <div>
                            {isBlocked ? (
                                <Button
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                    onClick={() => {
                                        onOpenChange(false);
                                        onUnblock?.(vendor);
                                    }}
                                >
                                    <ShieldCheck className="size-4 mr-1.5" />
                                    Buka Blokir Vendor
                                </Button>
                            ) : (
                                <Button
                                    variant="destructive"
                                    onClick={() => {
                                        onOpenChange(false);
                                        onBlock?.(vendor);
                                    }}
                                >
                                    <ShieldAlert className="size-4 mr-1.5" />
                                    Blokir Rekanan Ini
                                </Button>
                            )}
                        </div>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
