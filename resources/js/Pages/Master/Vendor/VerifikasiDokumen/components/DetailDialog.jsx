import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import {
    AlertTriangle,
    Building2,
    Download,
    FileText,
    Mail,
    MapPin,
    Phone,
    ShieldAlert,
} from 'lucide-react';
import { VerificationStatusBadge } from '../columns';

export function DetailDialog({ vendor, open, onOpenChange, onApprove, onReject }) {
    if (!vendor) return null;

    const documents = vendor.documents ?? [];

    // Approval/rejection dialogs need the vendor name next to the document.
    const withVendor = (doc) => ({ ...doc, vendor });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FileText className="size-5 text-emerald-600" />
                        Detail Legalitas Vendor
                    </DialogTitle>
                    <DialogDescription>
                        Profil calon vendor dan seluruh dokumen legalitas yang diunggah.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2 text-sm">
                    {/* Warning Duplikasi Dokumen */}
                    {vendor.has_duplicate_documents && Array.isArray(vendor.duplicate_warnings) && vendor.duplicate_warnings.length > 0 && (
                        <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3.5 space-y-2.5">
                            <div className="flex items-start gap-2.5 text-amber-800 dark:text-amber-300">
                                <AlertTriangle className="size-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                                <div>
                                    <h4 className="font-semibold text-xs sm:text-sm">
                                        Peringatan: Duplikasi Nomor Dokumen Legalitas
                                    </h4>
                                    <p className="text-[11px] leading-relaxed opacity-90 mt-0.5">
                                        Nomor dokumen pada pendaftaran ini terdeteksi telah digunakan oleh entitas lain. Pastikan verifikasi hubungan resmi (misalnya kantor cabang atau direksi yang sama) untuk mencegah impersonation.
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-1.5 pt-1 border-t border-amber-500/20 text-xs">
                                {vendor.duplicate_warnings.map((warn, wIdx) => (
                                    <div key={wIdx} className="p-2 rounded bg-background/80 border border-amber-500/20 space-y-1">
                                        <div className="flex items-center justify-between text-[11px]">
                                            <span className="font-medium text-foreground">{warn.document_label}: <strong className="font-mono">{warn.document_number}</strong></span>
                                            <span className="text-[10px] text-muted-foreground">{warn.matches?.length || 0} entitas serupa</span>
                                        </div>
                                        <div className="space-y-1 pl-2 border-l-2 border-amber-500/40 text-[11px]">
                                            {warn.matches?.map((match, mIdx) => (
                                                <div key={mIdx} className="flex flex-wrap items-center gap-1.5 text-muted-foreground">
                                                    <span className="font-medium text-foreground">{match.company_name}</span>
                                                    <span className="capitalize px-1 py-0.2 rounded bg-muted text-[10px]">
                                                        {match.entity_type === 'vendor' ? 'Vendor (Pemasok)' : 'Tenant (Pembeli)'}
                                                    </span>
                                                    <span className="text-[10px]">
                                                        ({match.verification_status === 'approved' ? 'Terverifikasi' : 'Menunggu Verifikasi'})
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Profil Vendor */}
                    <div className="rounded-lg border bg-muted/30 p-3 space-y-2">
                        <div className="flex items-center justify-between gap-2">
                            <div className="flex items-center gap-2 font-semibold text-foreground">
                                <Building2 className="size-4 text-emerald-600 shrink-0" />
                                <span>{vendor.company_name}</span>
                            </div>
                            <VerificationStatusBadge status={vendor.verification_status} />
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-muted-foreground pt-1 border-t">
                            <div className="flex items-center gap-1.5">
                                <Mail className="size-3.5" />
                                <span className="truncate">{vendor.contact_email || '-'}</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Phone className="size-3.5" />
                                <span>{vendor.phone || '-'}</span>
                            </div>
                        </div>
                        {vendor.kategori_komoditas && (
                            <div className="text-xs">
                                <span className="text-muted-foreground">Komoditas: </span>
                                <span className="font-medium text-foreground">
                                    {vendor.kategori_komoditas}
                                </span>
                            </div>
                        )}
                        <div className="flex items-center gap-3 text-xs pt-1 border-t">
                            <div>
                                <span className="text-muted-foreground">NPWP: </span>
                                <span className="font-medium text-foreground">{vendor.tax_number || '-'}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Status PKP: </span>
                                <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium ${vendor.is_pkp ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'}`}>
                                    {vendor.is_pkp ? 'PKP (PPN)' : 'Non-PKP (Non PPN)'}
                                </span>
                            </div>
                        </div>
                        {vendor.address && (
                            <div className="flex items-start gap-1.5 text-xs text-muted-foreground">
                                <MapPin className="size-3.5 shrink-0 mt-0.5" />
                                <span>{vendor.address}</span>
                            </div>
                        )}
                    </div>

                    {/* Dokumen */}
                    <div className="space-y-2">
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Berkas Legalitas ({documents.length})
                        </h4>

                        {documents.map((doc) => {
                            const isPending = doc.verification_status === 'pending';
                            const docLabel =
                                doc.document_type === 'npwp'
                                    ? 'NPWP Perusahaan'
                                    : doc.document_type === 'nib'
                                        ? 'NIB'
                                        : doc.document_type === 'ktp'
                                            ? 'KTP Penanggung Jawab'
                                            : doc.document_type === 'sppkp'
                                                ? 'SPPKP (Pengusaha Kena Pajak)'
                                                : doc.document_type === 'akta' || doc.document_type === 'akta_pendirian'
                                                    ? 'Akta Pendirian & SK Kemenkumham'
                                                    : doc.document_type === 'siup'
                                                        ? 'SIUP'
                                                        : (doc.document_type || 'Dokumen');

                            return (
                                <div key={doc.uuid} className="rounded-lg border p-3 space-y-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-medium text-foreground">{docLabel}</p>
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {doc.document_number || '-'}
                                            </p>
                                        </div>
                                        <VerificationStatusBadge status={doc.verification_status} />
                                    </div>

                                    {doc.verified_at && (
                                        <p className="text-xs text-muted-foreground">
                                            Diverifikasi pada{' '}
                                            <span className="font-medium text-foreground">
                                                {new Date(doc.verified_at).toLocaleString('id-ID')}
                                            </span>
                                            {doc.verifier?.name && <> oleh {doc.verifier.name}</>}
                                        </p>
                                    )}

                                    {doc.rejection_reason && (
                                        <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-2.5 text-xs space-y-1">
                                            <div className="flex items-center gap-1.5 font-semibold text-destructive">
                                                <ShieldAlert className="size-3.5" />
                                                <span>Alasan Penolakan:</span>
                                            </div>
                                            <p className="text-foreground leading-relaxed whitespace-pre-wrap">
                                                {doc.rejection_reason}
                                            </p>
                                        </div>
                                    )}

                                    <div className="flex flex-wrap items-center justify-between gap-2 pt-1">
                                        {doc.file_url && (
                                            <Button variant="outline" size="sm" asChild className="gap-2 text-xs">
                                                <a href={doc.file_url} target="_blank" rel="noreferrer">
                                                    <Download className="size-3.5" />
                                                    Buka / Unduh Berkas
                                                </a>
                                            </Button>
                                        )}
                                        {isPending && (
                                            <div className="flex gap-2">
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => {
                                                        onOpenChange(false);
                                                        onReject(withVendor(doc));
                                                    }}
                                                >
                                                    Tolak
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                                    onClick={() => {
                                                        onOpenChange(false);
                                                        onApprove(withVendor(doc));
                                                    }}
                                                >
                                                    Setujui
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Email persetujuan dikirim ke vendor setelah <strong>semua</strong> dokumen disetujui.
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Tutup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
