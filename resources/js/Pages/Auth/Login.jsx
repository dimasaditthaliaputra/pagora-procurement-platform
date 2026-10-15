import { Head, Link } from '@inertiajs/react';

export default function Login() {
    return (
        <div className="min-h-screen bg-cream-paper text-ink-black flex flex-col justify-between p-4 sm:p-8 font-sans selection:bg-fresh-grass">
            <Head title="Masuk Portal Pengadaan — PAGORA" />
            
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

            <div className="max-w-md w-full mx-auto bg-pure-white rounded-[40px] sm:rounded-[50px] border border-hairline-mist p-8 sm:p-10 my-8">
                <div className="text-center mb-8">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-[50px] bg-cream-paper border border-hairline-mist text-xs font-semibold text-ink-black mb-4">
                        <span className="w-2 h-2 rounded-full bg-fresh-grass" />
                        Portal Akses Terintegrasi
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight text-ink-black mb-2">
                        Masuk Portal
                    </h1>
                    <p className="text-sm text-stone-gray">
                        Akses sistem pengadaan bahan baku terpadu PAGORA
                    </p>
                </div>

                <form onSubmit={(e) => e.preventDefault()} className="space-y-4">
                    <div>
                        <label className="block text-xs font-semibold uppercase tracking-wider text-ink-black mb-2">
                            Alamat Email Perusahaan
                        </label>
                        <input 
                            type="email" 
                            placeholder="nama@perusahaan.co.id"
                            defaultValue="buyer@manufaktur.id"
                            className="w-full px-5 py-3.5 rounded-[50px] bg-cream-paper border border-hairline-mist text-sm text-ink-black focus:outline-none focus:border-ink-black transition-colors"
                        />
                    </div>

                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <label className="block text-xs font-semibold uppercase tracking-wider text-ink-black">
                                Kata Sandi
                            </label>
                            <a href="#forgot" className="text-xs text-stone-gray hover:underline">
                                Lupa sandi?
                            </a>
                        </div>
                        <input 
                            type="password" 
                            defaultValue="••••••••••••"
                            className="w-full px-5 py-3.5 rounded-[50px] bg-cream-paper border border-hairline-mist text-sm text-ink-black focus:outline-none focus:border-ink-black transition-colors"
                        />
                    </div>

                    <div className="pt-2">
                        <button 
                            type="button" 
                            className="w-full py-3.5 px-6 rounded-[50px] bg-ink-black hover:bg-pure-ink text-pure-white font-medium text-sm transition-all flex items-center justify-center gap-2"
                        >
                            <span>Masuk ke Dashboard</span>
                            <span className="w-2 h-2 rounded-full bg-fresh-grass" />
                        </button>
                    </div>
                </form>

                <div className="mt-8 pt-6 border-t border-cream-paper text-center text-xs text-stone-gray">
                    Belum terdaftar sebagai vendor mitra?{' '}
                    <Link href="/register-vendor" className="font-semibold text-ink-black hover:underline">
                        Daftar di sini
                    </Link>
                </div>
            </div>

            <div className="text-center text-xs text-stone-gray py-4">
                © {new Date().getFullYear()} PAGORA Enterprise E-Procurement. Hak Cipta Dilindungi.
            </div>
        </div>
    );
}
