import React from 'react';
import { Link } from '@inertiajs/react';
import landingData from '@/data/landingData.json';

export default function Footer() {
    return (
        <footer className="pt-16 border-t border-sandstone bg-cream-paper">
            <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
                <div className="grid grid-cols-1 md:grid-cols-12 gap-10">
                    <div className="md:col-span-5 space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-[14px] bg-fresh-grass border border-ink-black flex items-center justify-center font-bold text-ink-black text-lg">
                                P
                            </div>
                            <span className="font-semibold text-2xl tracking-tight text-ink-black">
                                PAGORA
                            </span>
                        </div>
                        <p className="text-sm text-stone-gray leading-relaxed max-w-sm">
                            Ekosistem E-Procurement B2B Bahan Baku Berbasis Harga Referensi Bank Indonesia dan Otomasi Validasi 3-Way Matching.
                        </p>
                        <div className="pt-2">
                            <span className="inline-flex items-center gap-2 px-3 py-1 rounded-[50px] bg-pure-white border border-hairline-mist text-xs font-medium text-ink-black">
                                <span className="w-2 h-2 rounded-full bg-fresh-grass" />
                                Standar Tata Kelola Transparan (GCG)
                            </span>
                        </div>
                    </div>

                    <div className="md:col-span-3 space-y-3">
                        <h4 className="text-xs font-bold uppercase tracking-wider text-ink-black">
                            Jelajahi Solusi
                        </h4>
                        <ul className="space-y-2 text-sm text-stone-gray">
                            {landingData.footerSolutions.map((item, idx) => (
                                <li key={idx}>
                                    <a href={item.href} className="hover:text-ink-black transition-colors">
                                        {item.title}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="md:col-span-4 space-y-3">
                        <h4 className="text-xs font-bold uppercase tracking-wider text-ink-black">
                            Kepatuhan & Akses Mitra
                        </h4>
                        <p className="text-xs text-stone-gray leading-relaxed">
                            Seluruh vendor terdaftar wajib mematuhi pakta integritas penetapan harga transparan, kepatuhan mutu uji lab, dan verifikasi faktur pajak resmi.
                        </p>
                        <div className="pt-2 flex flex-col gap-2">
                            <Link
                                href="/login"
                                className="text-xs font-semibold text-ink-black hover:underline"
                            >
                                → Masuk Portal Pengadaan Internal
                            </Link>
                            <Link
                                href="/register-vendor"
                                className="text-xs font-semibold text-ink-black hover:underline"
                            >
                                → Pendaftaran Rekanan Vendor Baru
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="mt-12 pt-6 border-t border-sandstone flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-stone-gray">
                    <div className="flex items-center gap-6">
                        <a href="#terms" className="hover:underline">Syarat & Ketentuan Layanan</a>
                        <a href="#privacy" className="hover:underline">Kebijakan Privasi</a>
                        <a href="#security" className="hover:underline">Keamanan Data Bank Indonesia</a>
                    </div>
                </div>
            </div>

            <div className="w-full bg-sunshine-pop text-ink-black py-6 sm:py-8 px-4 text-center font-medium border-t-2 border-ink-black">
                <div className="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-sm sm:text-body-sm">
                    <div className="flex items-center gap-2">
                        <span className="w-3 h-3 rounded-full bg-ink-black" />
                        <span className="font-bold tracking-tight">PAGORA B2B ECOSYSTEM</span>
                        <span className="text-stone-gray hidden md:inline">|</span>
                        <span className="hidden md:inline text-xs font-normal">
                            Transparansi Terpercaya untuk Industri Manufaktur Indonesia
                        </span>
                    </div>
                    <div className="text-xs font-semibold">
                        Platform E-Procurement B2B Berbasis Harga Pagu & 3-Way Matching Otomatis
                    </div>
                </div>
            </div>
        </footer>
    );
}
