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
    Calendar,
    Download,
    FileCheck2,
    FileText,
    Mail,
    MapPin,
    ShieldAlert,
    User as UserIcon,
} from 'lucide-react';
import { VerificationStatusBadge } from '../columns';

export function DetailDialog({ tenant, open, onOpenChange, onApprove, onReject }) {
    if (!tenant) return null;

    const isPending = tenant.verification_status === 'pending';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FileCheck2 className="size-5 text-emerald-600" />
                        Detail Pendaftaran & Legalitas Tenant
                    </DialogTitle>
                    <DialogDescription>
                        Informasi lengkap pendaftaran tenant, identitas pemilik, dan berkas legalitas.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2 text-sm">
                    {/* Profil Perusahaan */}
                    <div className="rounded-lg border bg-muted/30 p-3.5 space-y-2.5">
                        <div className="flex items-center justify-between gap-2">
                            <div className="flex items-center gap-2 font-semibold text-foreground">
                                <Building2 className="size-4 text-emerald-600 shrink-0" />
                                <span className="text-base">{tenant.company_name}</span>
                            </div>
                            <VerificationStatusBadge status={tenant.verification_status} />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs pt-1 border-t">
                            <div>
                                <span className="text-muted-foreground">NPWP Perusahaan: </span>
                                <span className="font-mono font-medium text-foreground">
                                    {tenant.tax_number || '-'}
                                </span>
                            </div>
                            {tenant.created_at && (
                                <div className="flex items-center gap-1.5 text-muted-foreground">
                                    <Calendar className="size-3.5" />
                                    <span>
                                        Didaftarkan:{' '}
                                        {new Date(tenant.created_at).toLocaleDateString('id-ID', {
                                            day: 'numeric',
                                            month: 'short',
                                            year: 'numeric',
                                        })}
                                    </span>
                                </div>
                            )}
                        </div>

                        {tenant.address && (
                            <div className="flex items-start gap-1.5 text-xs text-muted-foreground pt-1">
                                <MapPin className="size-3.5 shrink-0 mt-0.5" />
                                <span className="text-foreground">{tenant.address}</span>
                            </div>
                        )}
                    </div>

                    {/* Identitas Pemilik / Akun Owner */}
                    <div className="rounded-lg border p-3.5 space-y-2">
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <UserIcon className="size-3.5" />
                            Data Pemilik / Akun Pendaftar
                        </h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div>
                                <span className="text-muted-foreground">Nama: </span>
                                <span className="font-medium text-foreground">
                                    {tenant.owner?.name || '-'}
                                </span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Mail className="size-3.5 text-muted-foreground" />
                                <span className="text-foreground">{tenant.owner?.email || '-'}</span>
                            </div>
                        </div>
                    </div>

                    {/* Dokumen Legalitas */}
                    <div className="rounded-lg border p-3.5 space-y-2.5">
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <FileText className="size-3.5" />
                            Berkas Legalitas
                        </h4>

                        {tenant.has_legal_document ? (
                            <div className="flex items-center justify-between gap-2 p-2 rounded-md bg-muted/40 border">
                                <div className="flex items-center gap-2">
                                    <FileText className="size-4 text-emerald-600 shrink-0" />
                                    <div>
                                        <p className="text-xs font-medium text-foreground">Dokumen Legalitas / SIUP / NIB</p>
                                        <p className="text-[11px] text-muted-foreground">Berkas persyaratan registrasi perusahaan</p>
                                    </div>
                                </div>
                                {tenant.legal_document_url && (
                                    <Button variant="outline" size="sm" asChild className="gap-1.5 text-xs h-8">
                                        <a href={tenant.legal_document_url} target="_blank" rel="noreferrer">
                                            <Download className="size-3.5" />
                                            Unduh Berkas
                                        </a>
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <p className="text-xs text-muted-foreground italic">
                                Belum ada berkas legalitas yang diunggah saat registrasi.
                            </p>
                        )}
                    </div>

                    {/* Riwayat Verifikasi */}
                    {tenant.verified_at && (
                        <div className="rounded-lg border p-3.5 space-y-1.5 bg-muted/20 text-xs">
                            <h4 className="text-xs font-semibold text-muted-foreground">Informasi Verifikasi</h4>
                            <p className="text-muted-foreground">
                                Status diverifikasi pada{' '}
                                <span className="font-medium text-foreground">
                                    {new Date(tenant.verified_at).toLocaleString('id-ID')}
                                </span>
                                {tenant.verifier?.name && <> oleh <strong className="text-foreground">{tenant.verifier.name}</strong></>}
                            </p>
                        </div>
                    )}

                    {/* Catatan / Alasan Penolakan */}
                    {tenant.verification_note && (
                        <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-xs space-y-1">
                            <div className="flex items-center gap-1.5 font-semibold text-destructive">
                                <ShieldAlert className="size-3.5" />
                                <span>Alasan Penolakan / Catatan Verifikasi:</span>
                            </div>
                            <p className="text-foreground leading-relaxed whitespace-pre-wrap">
                                {tenant.verification_note}
                            </p>
                        </div>
                    )}
                </div>

                <DialogFooter className="flex flex-wrap items-center justify-between gap-2 pt-2 sm:justify-between">
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onOpenChange(false)}
                        >
                            Tutup
                        </Button>
                    </div>

                    {isPending && (
                        <div className="flex items-center gap-2">
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => {
                                    onOpenChange(false);
                                    onReject(tenant);
                                }}
                            >
                                Tolak
                            </Button>
                            <Button
                                size="sm"
                                className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                onClick={() => {
                                    onOpenChange(false);
                                    onApprove(tenant);
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

