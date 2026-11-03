import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Building2, MapPin, ShieldCheck } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';

export default function Show({ buyer }) {
    const handleSubmit = () => {
        if (!confirm(`Ajukan kerja sama dengan ${buyer.company_name}?`)) {
            return;
        }

        router.post(`/kerja-sama/calon-mitra/${buyer.uuid}/ajukan`);
    };

    return (
        <>
            <Head title="Detail Calon Mitra" />

            <div className="space-y-6 p-6">
                <div>
                    <Button variant="ghost" asChild>
                        <Link href="/kerja-sama/calon-mitra">
                            <ArrowLeft className="mr-2 size-4" />
                            Kembali ke Daftar
                        </Link>
                    </Button>
                </div>

                <div className="rounded-lg border bg-white p-6 shadow-sm">
                    <div className="flex items-start gap-4">
                        <div className="rounded-lg bg-primary/10 p-3">
                            <Building2 className="size-8 text-primary" />
                        </div>

                        <div className="flex-1 space-y-2">
                            <h1 className="text-2xl font-bold">
                                {buyer.company_name}
                            </h1>

                            <div className="flex flex-wrap gap-2">
                                <Badge variant="secondary">
                                    <ShieldCheck className="mr-1 size-3" />
                                    Terverifikasi
                                </Badge>

                                <Badge variant="outline">
                                    Aktif
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div className="mt-6 border-t pt-6">
                        <h2 className="mb-3 font-semibold">
                            Informasi Perusahaan
                        </h2>

                        <div className="flex items-start gap-2 text-sm text-muted-foreground">
                            <MapPin className="mt-0.5 size-4 shrink-0" />
                            <span>{buyer.address || 'Alamat belum tersedia'}</span>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end border-t pt-6">
                        <Button onClick={handleSubmit}>
                            Ajukan Kerja Sama
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
