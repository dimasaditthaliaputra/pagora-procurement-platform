import { UserCircle, Mail, Shield, Calendar, Clock, Edit, CheckCircle2, XCircle, Building2 } from 'lucide-react';
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
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';

function getInitials(name) {
    if (!name) return 'U';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

function formatDate(isoString) {
    if (!isoString) return '-';
    try {
        const date = new Date(isoString);
        return new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    } catch {
        return isoString;
    }
}

export function PegawaiDetailModal({
    open,
    onOpenChange,
    pegawai = null,
    canEdit = true,
    onEdit,
}) {
    if (!pegawai) return null;

    const roleName = pegawai.role?.name;
    const roleLabel = pegawai.role?.label ?? 'User';
    const perusahaan = pegawai.perusahaan;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[480px] p-0 overflow-hidden">
                <DialogHeader className="p-6 pb-4 border-b bg-muted/20">
                    <div className="flex items-center gap-2.5">
                        <div className="p-2 rounded-lg bg-primary/10 text-primary">
                            <UserCircle className="size-5" />
                        </div>
                        <div>
                            <DialogTitle className="text-lg font-bold">
                                Detail Akun Pengguna
                            </DialogTitle>
                            <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                                Kredensial, afiliasi perusahaan, dan hak akses pengguna pada sistem PAGORA.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="p-6 space-y-5">
                    {/* Header Profile Section */}
                    <div className="flex items-center gap-4 p-4 rounded-xl border bg-card/60 backdrop-blur-xs">
                        <Avatar className="size-14 rounded-full border-2 border-primary/20">
                            <AvatarImage src={pegawai.avatar} alt={pegawai.nama_lengkap} />
                            <AvatarFallback className="text-base font-bold bg-primary/10 text-primary">
                                {getInitials(pegawai.nama_lengkap)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex flex-col gap-1 min-w-0">
                            <h3 className="font-bold text-base text-foreground truncate">
                                {pegawai.nama_lengkap}
                            </h3>
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground truncate">
                                <Mail className="size-3.5 shrink-0" />
                                <span className="truncate">{pegawai.email}</span>
                            </div>
                            <div className="flex items-center gap-2 mt-1">
                                {pegawai.status_aktif ? (
                                    <Badge className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/20 text-[11px] font-medium py-0 h-5">
                                        <CheckCircle2 className="size-3 mr-1" />
                                        Akun Aktif
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary" className="text-muted-foreground text-[11px] font-medium py-0 h-5">
                                        <XCircle className="size-3 mr-1" />
                                        Akun Non-aktif
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Company & Role Info */}
                    <div className="space-y-3">
                        <div className="p-3.5 rounded-lg border bg-muted/20 flex items-center justify-between gap-3">
                            <div className="space-y-0.5 min-w-0">
                                <span className="text-xs font-semibold text-muted-foreground flex items-center gap-1.5">
                                    <Building2 className="size-3.5 text-primary shrink-0" />
                                    Perusahaan / Organisasi
                                </span>
                                <p className="text-sm font-semibold text-foreground truncate">
                                    {perusahaan ? perusahaan.nama : 'Internal Platform (PAGORA)'}
                                </p>
                            </div>
                            <Badge variant="secondary" className="text-[11px] shrink-0">
                                {!perusahaan
                                    ? 'Platform'
                                    : perusahaan.tipe === 'vendor'
                                      ? 'Rekanan Vendor'
                                      : 'Perusahaan Buyer'}
                            </Badge>
                        </div>

                        <div className="p-3.5 rounded-lg border bg-muted/20 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-muted-foreground flex items-center gap-1.5">
                                    <Shield className="size-3.5 text-primary" />
                                    Peran & Otoritas Sistem
                                </span>
                                <Badge variant="outline" className="font-semibold text-xs">
                                    {roleLabel}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground leading-relaxed">
                                {pegawai.role?.deskripsi ||
                                    (roleName === 'buyer'
                                        ? 'Bertanggung jawab atas pengadaan barang, penawaran harga acuan, dan penerbitan PO.'
                                        : 'Bertanggung jawab atas operasional sesuai hak akses perusahaannya.')}
                            </p>
                        </div>

                        {/* Timestamps */}
                        <div className="grid grid-cols-2 gap-3 text-xs">
                            <div className="p-3 rounded-lg border bg-muted/10 space-y-1">
                                <span className="text-muted-foreground flex items-center gap-1">
                                    <Calendar className="size-3 text-muted-foreground" />
                                    Terdaftar Pada
                                </span>
                                <p className="font-medium text-foreground">
                                    {formatDate(pegawai.created_at)}
                                </p>
                            </div>
                            <div className="p-3 rounded-lg border bg-muted/10 space-y-1">
                                <span className="text-muted-foreground flex items-center gap-1">
                                    <Clock className="size-3 text-muted-foreground" />
                                    Terakhir Diperbarui
                                </span>
                                <p className="font-medium text-foreground">
                                    {formatDate(pegawai.updated_at)}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter className="p-4 px-6 border-t bg-muted/20 flex flex-row items-center justify-between">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => onOpenChange(false)}
                        className="h-9 cursor-pointer"
                    >
                        Tutup
                    </Button>
                    {canEdit && (
                        <Button
                            type="button"
                            onClick={() => {
                                onOpenChange(false);
                                onEdit(pegawai);
                            }}
                            className="h-9 gap-1.5 cursor-pointer shadow-sm"
                        >
                            <Edit className="size-4" />
                            Ubah Akun
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
