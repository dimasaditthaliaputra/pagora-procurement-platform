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

                            return (
                                <div key={doc.uuid} className="rounded-lg border p-3 space-y-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-medium text-foreground">{doc.document_type}</p>
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
