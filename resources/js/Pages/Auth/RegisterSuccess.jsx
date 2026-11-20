import { useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Info, ArrowRight } from 'lucide-react';
import { playSound } from '@/lib/sound';
import Logo from '../../../assets/logo/logo.webp';
import SuccessAnimation from '../../../assets/Success.svg';

export default function RegisterSuccess({ email = null }) {
    useEffect(() => {
        playSound('success');
    }, []);

    return (
        <div className="min-h-dvh w-full flex items-center justify-center bg-background sm:bg-muted/20 p-4 sm:p-6 lg:p-8 font-inter select-none">
            <Head title="Pendaftaran Berhasil - PAGORA" />

            {/* Center Content Card */}
            <main className="w-full max-w-md my-auto">
                <div className="bg-card rounded-2xl border border-border/80 shadow-xs p-6 sm:p-8 text-center space-y-5">
                    {/* Brand Logo di dalam Card */}
                    <div className="flex justify-center pb-1">
                        <Link href="/" className="inline-flex items-center gap-2.5 group">
                            <div className="size-9 rounded-xl bg-card border border-border flex items-center justify-center p-1.5 shadow-2xs group-hover:scale-105 transition-transform">
                                <img src={Logo} alt="Logo PAGORA" className="size-full object-contain" />
                            </div>
                            <div className="flex flex-col text-left">
                                <span className="font-bold tracking-tight text-base sm:text-lg text-foreground leading-none">
                                    PAGORA
                                </span>
                                <span className="text-[10px] text-muted-foreground font-medium">
                                    E-Procurement Platform
                                </span>
                            </div>
                        </Link>
                    </div>

                    {/* Animasi Icon Check Sukses */}
                    <div className="flex justify-center">
                        <img
                            src={SuccessAnimation}
                            alt="Pendaftaran Berhasil"
                            className="size-24 sm:size-28 object-contain"
                        />
                    </div>

                    {/* Heading */}
                    <div className="space-y-1.5">
                        <h1 className="text-2xl sm:text-[25px] font-bold tracking-tight text-foreground leading-tight">
                            Pendaftaran Berhasil
                        </h1>
                        <p className="text-sm text-muted-foreground leading-relaxed">
                            Pendaftaran telah berhasil diterima oleh sistem. Silakan periksa kotak masuk (inbox) email Anda secara berkala untuk memantau status akun.
                        </p>
                        {email && (
                            <p className="text-xs text-muted-foreground pt-0.5">
                                Notifikasi dikirimkan ke:{' '}
                                <span className="font-semibold text-foreground">{email}</span>
                            </p>
                        )}
                    </div>

                    {/* Informasi Tambahan: 1x24 Jam & Folder Spam */}
                    <div className="rounded-xl border border-border/80 bg-muted/30 p-3.5 text-left flex items-start gap-3">
                        <Info className="size-4 text-muted-foreground shrink-0 mt-0.5" />
                        <p className="text-xs text-muted-foreground leading-relaxed">
                            Jika email belum diterima dalam kurun waktu 1×24 jam, harap periksa folder{' '}
                            <span className="font-semibold text-foreground">Spam</span>.
                        </p>
                    </div>

                    {/* Tombol Aksi Menuju Halaman Sign In */}
                    <div className="pt-1 space-y-2.5">
                        <Button
                            asChild
                            className="w-full h-11 text-sm font-semibold rounded-xl bg-primary text-primary-foreground hover:bg-primary/90 shadow-2xs cursor-pointer gap-2 transition-all"
                        >
                            <Link href="/login">
                                <span>Menuju Halaman Sign In</span>
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>

                        <div className="text-center">
                            <Link
                                href="/"
                                className="text-xs text-muted-foreground hover:text-foreground transition-colors"
                            >
                                Kembali ke Beranda
                            </Link>
                        </div>
                    </div>

                    {/* Copyright di dalam Card */}
                    <div className="pt-3 border-t border-border/60 text-center">
                        <p className="text-[11px] text-muted-foreground/80">
                            © {new Date().getFullYear()} PAGORA · Ekosistem Pengadaan B2B Terpercaya
                        </p>
                    </div>
                </div>
            </main>
        </div>
    );
}

