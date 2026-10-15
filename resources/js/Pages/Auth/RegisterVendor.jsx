import { Head, Link } from '@inertiajs/react';

export default function RegisterVendor() {
    return (
        <div className="min-h-screen bg-cream-paper text-ink-black flex flex-col justify-between p-4 sm:p-8 font-sans selection:bg-fresh-grass">
            <Head title="Registrasi Vendor Mitra — PAGORA" />
            
            <div className="max-w-4xl mx-auto w-full flex items-center justify-between py-4">
                <Link href="/" className="inline-flex items-center gap-3 group">
                    <div className="w-10 h-10 rounded-xl bg-fresh-grass border border-ink-black flex items-center justify-center font-bold text-ink-black text-lg">
                        P
                    </div>
                    <span className="font-semibold tracking-tight text-xl text-ink-black">PAGORA</span>
                </Link>
                <Link 
                    href="/"
                    className="inline-flex items-center gap-2 text-sm font-medium text-ink-black hover:opacity-80 transition-opacity bg-pure-white px-4 py-2 rounded-[50px] border border-hairline-mist"
                >
                    ← Kembali ke Beranda
                </Link>
            </div>

            <div className="max-w-xl w-full mx-auto bg-pure-white rounded-[40px] sm:rounded-[50px] border border-hairline-mist p-8 sm:p-10 my-8">
                <div className="text-center mb-8">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-[50px] bg-cream-paper border border-hairline-mist text-xs font-semibold text-ink-black mb-4">
                        <span className="w-2 h-2 rounded-full bg-coral-pop" />
                        Kemitraan Vendor Terverifikasi
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight text-ink-black mb-2">
                        Daftar Vendor Mitra
                    </h1>
                    <p className="text-sm text-stone-gray">
                        Dapatkan akses ke pesanan bahan baku manufaktur berulang dengan jaminan transparansi PO & ketepatan pembayaran 3-Way Matching.
                    </p>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-ink-black mb-2">
                                Nama Perusahaan / PT / CV
                            </label>
                            <input 
                                type="text" 
                                placeholder="PT Agro Sumber Makmur"
                                className="w-full px-5 py-3.5 rounded-[50px] bg-cream-paper border border-hairline-mist text-sm text-ink-black focus:outline-none focus:border-ink-black transition-colors"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-ink-black mb-2">
                                Kategori Bahan Baku
                            </label>
                            <select 
                                className="w-full px-5 py-3.5 rounded-[50px] bg-cream-paper border border-hairline-mist text-sm text-ink-black focus:outline-none focus:border-ink-black transition-colors"
                            >
                                <option>Agroindustri & Pangan</option>
                                <option>Kimia Industri & Polimer</option>
                                <option>Logam, Baja & Fabrikasi</option>
                                <option>Tekstil & Serat Alami</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-ink-black mb-2">
                                Nomor NPWP Perusahaan
                            </label>
                            <input 
                                type="text" 
                                placeholder="01.234.567.8-901.000"
                                className="w-full px-5 py-3.5 rounded-[50px] bg-cream-paper border border-hairline-mist text-sm text-ink-black focus:outline-none focus:border-ink-black transition-colors"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-ink-black mb-2">
                                PIC & No. WhatsApp
                            </label>
                            <input 
                                type="text" 
                                placeholder="+62 812-3456-7890"
                                className="w-full px-5 py-3.5 rounded-[50px] bg-cream-paper border border-hairline-mist text-sm text-ink-black focus:outline-none focus:border-ink-black transition-colors"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-ink-black mb-2">
                            Email Bisnis Resmi
                        </label>
                        <input 
                            type="email" 
                            placeholder="sales@agrosumbermakmur.com"
                            className="w-full px-5 py-3.5 rounded-[50px] bg-cream-paper border border-hairline-mist text-sm text-ink-black focus:outline-none focus:border-ink-black transition-colors"
                        />
                    </div>

                    <div className="pt-2">
                        <button 
                            type="button" 
                            className="w-full py-3.5 px-6 rounded-[50px] bg-coral-pop hover:opacity-90 text-pure-white font-medium text-sm transition-all flex items-center justify-center gap-2"
                        >
                            <span>Kirim Pendaftaran Vendor</span>
                            <span className="w-2 h-2 rounded-full bg-pure-white" />
                        </button>
                    </div>
                </form>

                <div className="mt-8 pt-6 border-t border-cream-paper text-center text-xs text-stone-gray">
                    Sudah memiliki akun vendor terdaftar?{' '}
                    <Link href="/login" className="font-semibold text-ink-black hover:underline">
                        Masuk di sini
                    </Link>
                </div>
            </div>

            <div className="text-center text-xs text-stone-gray py-4">
                © {new Date().getFullYear()} PAGORA Enterprise E-Procurement. Hak Cipta Dilindungi.
            </div>
        </div>
    );
}
