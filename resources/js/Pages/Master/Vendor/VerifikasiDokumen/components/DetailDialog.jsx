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
    Download,
    FileText,
    Mail,
    MapPin,
    Phone,
    ShieldAlert,
    XCircle,
} from 'lucide-react';

export function DetailDialog({ document, open, onOpenChange, onApprove, onReject }) {
    if (!document) return null;

    const vendor = document.vendor;
    const isPending = document.status_verifikasi === 'pending';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FileText className="size-5 text-emerald-600" />
                        Detail Legalitas Vendor
                    </DialogTitle>
                    <DialogDescription>
                        Informasi lengkap dokumen perizinan dan profil calon vendor.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2 text-sm">
                    {/* Profil Vendor */}
                    <div className="rounded-lg border bg-muted/30 p-3 space-y-2">
                        <div className="flex items-center gap-2 font-semibold text-foreground">
                            <Building2 className="size-4 text-emerald-600 shrink-0" />
                            <span>{vendor?.nama_perusahaan ?? 'Vendor Tidak Dikenal'}</span>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-muted-foreground pt-1 border-t">
                            <div className="flex items-center gap-1.5">
                                <Mail className="size-3.5" />
                                <span className="truncate">{vendor?.email_kontak || '-'}</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Phone className="size-3.5" />
                                <span>{vendor?.no_telepon || '-'}</span>
                            </div>
                        </div>
                        {vendor?.kategori_komoditas && (
                            <div className="text-xs">
                                <span className="text-muted-foreground">Komoditas: </span>
                                <span className="font-medium text-foreground">
                                    {vendor.kategori_komoditas}
                                </span>
                            </div>
                        )}
                        {vendor?.alamat && (
                            <div className="flex items-start gap-1.5 text-xs text-muted-foreground">
                                <MapPin className="size-3.5 shrink-0 mt-0.5" />
                                <span>{vendor.alamat}</span>
                            </div>
                        )}
                    </div>

                    {/* Rincian Dokumen */}
                    <div className="space-y-2">
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Berkas Legalitas
                        </h4>
                        <div className="grid grid-cols-2 gap-3 text-xs">
                            <div className="rounded-lg border p-2.5">
                                <span className="text-muted-foreground block text-[11px]">
                                    Jenis Dokumen
                                </span>
                                <span className="font-medium text-foreground">
                                    {document.jenis_dokumen}
                                </span>
                            </div>
                            <div className="rounded-lg border p-2.5">
                                <span className="text-muted-foreground block text-[11px]">
                                    Nomor Dokumen
                                </span>
                                <span className="font-mono font-medium text-foreground">
                                    {document.nomor_dokumen || '-'}
                                </span>
                            </div>
                        </div>

                        {document.file_url && (
                            <div className="pt-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    className="w-full gap-2 text-xs"
                                >
                                    <a
                                        href={document.file_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Download className="size-3.5" />
                                        Buka / Unduh Berkas Lampiran
                                    </a>
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Status & Catatan Verifikasi */}
                    <div className="space-y-2 pt-1 border-t">
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-muted-foreground">Status Verifikasi</span>
                            {document.status_verifikasi === 'approved' && (
                                <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 border-emerald-500/20 inline-flex items-center gap-1.5">
                                    <CheckCircle2 className="size-3" /> Disetujui
                                </Badge>
                            )}
                            {document.status_verifikasi === 'rejected' && (
                                <Badge variant="outline" className="bg-destructive/10 text-destructive border-destructive/20 inline-flex items-center gap-1.5">
                                    <XCircle className="size-3" /> Ditolak
                                </Badge>
                            )}
                            {document.status_verifikasi === 'pending' && (
                                <Badge variant="outline" className="bg-amber-500/10 text-amber-600 border-amber-500/20 inline-flex items-center gap-1.5">
                                    <Clock className="size-3" /> Menunggu Validasi
                                </Badge>
                            )}
                        </div>

                        {document.diverifikasi_pada && (
                            <div className="text-xs text-muted-foreground">
                                Diverifikasi pada{' '}
                                <span className="font-medium text-foreground">
                                    {new Date(document.diverifikasi_pada).toLocaleString('id-ID')}
                                </span>
                                {document.verifier?.nama_lengkap && (
                                    <> oleh {document.verifier.nama_lengkap}</>
                                )}
                            </div>
                        )}

                        {document.alasan_penolakan && (
                            <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-xs space-y-1">
                                <div className="flex items-center gap-1.5 font-semibold text-destructive">
                                    <ShieldAlert className="size-3.5" />
                                    <span>Alasan Penolakan:</span>
                                </div>
                                <p className="text-foreground leading-relaxed whitespace-pre-wrap">
                                    {document.alasan_penolakan}
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Tutup
                    </Button>
                    {isPending && (
                        <div className="flex gap-2">
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => {
                                    onOpenChange(false);
                                    onReject(document);
                                }}
                            >
                                Tolak
                            </Button>
                            <Button
                                size="sm"
                                className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                onClick={() => {
                                    onOpenChange(false);
                                    onApprove(document);
                                }}
                            >
                                Setujui
                            </Button>
                        </div>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
