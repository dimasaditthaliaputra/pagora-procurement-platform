import { Head, usePage } from '@inertiajs/react';
import { MotionConfig } from 'framer-motion';
import BundledLogo from '../../assets/logo/logo.webp';
import AnimatedValueHeading from './Home/components/AnimatedValueHeading';
import ClosingBanner from './Home/components/ClosingBanner';
import CommodityMarquee from './Home/components/CommodityMarquee';
import FeatureTabsShowcase from './Home/components/FeatureTabsShowcase';
import GetStartedCards from './Home/components/GetStartedCards';
import HeroSection from './Home/components/HeroSection';
import LandingButton from './Home/components/LandingButton';
import LandingFooter from './Home/components/LandingFooter';
import LandingNavbar from './Home/components/LandingNavbar';
import ProblemAccordion from './Home/components/ProblemAccordion';
import RoleStackedCards from './Home/components/RoleStackedCards';
import SafeguardCards from './Home/components/SafeguardCards';
import SectionHeading from './Home/components/SectionHeading';
import SmoothScrollProvider from './Home/components/SmoothScrollProvider';
import StatusTicker from './Home/components/StatusTicker';
import WorkflowCarousel from './Home/components/WorkflowCarousel';

const SECTION_SPACING = 'pt-20 desk:pt-32';

export default function Home() {
    const { auth, aplikasi } = usePage().props;
    const isAuthenticated = Boolean(auth?.user);
    const appName = aplikasi?.nama || 'PAGORA';
    const logoUrl = aplikasi?.logo_url || BundledLogo;

    return (
        <>
            <Head title="Pengadaan Bahan Baku B2B" />

            <MotionConfig reducedMotion="user">
                <SmoothScrollProvider>
                    <div className="landing-page min-h-screen overflow-x-clip">
                        <a
                            href="#konten"
                            className="sr-only rounded-full bg-brand-offblack px-4 py-2 text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50"
                        >
                            Lewati ke konten
                        </a>

                        <LandingNavbar isAuthenticated={isAuthenticated} appName={appName} logoUrl={logoUrl} />

                        <main id="konten">
                            <HeroSection isAuthenticated={isAuthenticated} appName={appName} logoUrl={logoUrl} />
                            <CommodityMarquee />

                            <section data-snap id="fitur" className={SECTION_SPACING}>
                                <AnimatedValueHeading
                                    before={'Harga, negosiasi, logistik, QC, tagihan.\n'}
                                    value={{ from: 1, to: 5 }}
                                    after=" pekerjaan, satu portal."
                                    description="Pekerjaan yang dulu tersebar di chat, telepon, dan kertas kini terhubung dalam satu alur, dari riset harga sampai tagihan diverifikasi."
                                    action={
                                        <LandingButton href="#alur" variant="tertiary">
                                            Lihat alur kerja
                                        </LandingButton>
                                    }
                                />
                                <FeatureTabsShowcase />
                            </section>

                            <section data-snap id="masalah" className={SECTION_SPACING}>
                                <AnimatedValueHeading
                                    value={{ from: 0, to: 3 }}
                                    after={' dokumen dicocokkan\nsebelum satu rupiah dibayar.'}
                                    description="PO, penerimaan gudang, dan invoice harus cocok sebelum pembayaran dibuka. Begitulah PAGORA menutup celah yang biasa membuat anggaran bocor."
                                />
                                <ProblemAccordion />
                            </section>

                            <section data-snap id="alur" className={SECTION_SPACING}>
                                <AnimatedValueHeading
                                    value={{ from: 1, to: 8 }}
                                    after={' tahap pengadaan.\nTidak ada yang tercecer.'}
                                    description="Dari kemitraan sampai pelunasan, setiap tahap punya pemilik yang jelas dan status yang bisa dilacak semua pihak."
                                />
                                <WorkflowCarousel />
                            </section>

                            <section data-snap id="peran" className={SECTION_SPACING}>
                                <SectionHeading
                                    title={'Kuat untuk perusahaan,\nmudah untuk vendor.'}
                                    description="Setiap peran hanya melihat dan mengerjakan bagiannya, dengan data yang sama untuk semua."
                                />
                                <RoleStackedCards />
                            </section>

                            <section data-snap id="keamanan" className={SECTION_SPACING}>
                                <AnimatedValueHeading
                                    value={{ from: 0, to: 100, suffix: '%' }}
                                    after={' riwayat transaksi\ntersimpan permanen.'}
                                    description="Tidak ada data transaksi yang dihapus permanen. Setiap keputusan meninggalkan jejak yang bisa diaudit."
                                />
                                <SafeguardCards />
                            </section>

                            <section data-snap id="mulai" className={SECTION_SPACING}>
                                <SectionHeading title="Mari mulai bekerja" description="Pilih cara Anda bergabung dengan PAGORA." />
                                <GetStartedCards isAuthenticated={isAuthenticated} />
                            </section>

                            <section data-snap aria-label="Ajakan bergabung" className={SECTION_SPACING}>
                                <ClosingBanner isAuthenticated={isAuthenticated} />
                            </section>

                            <section data-snap aria-label="Status Purchase Order" className="mt-20 py-20 desk:mt-32 desk:py-28">
                                <SectionHeading eyebrow="Status yang dilacak" title="Setiap PO, sampai selesai." className="mb-10 desk:mb-14" />
                                <StatusTicker />
                                <div className="landing-container mt-10 flex flex-col justify-center gap-3 tab:flex-row desk:mt-14">
                                    <LandingButton href={isAuthenticated ? '/dashboard' : '/register'} size="responsive">
                                        {isAuthenticated ? 'Buka Dasbor' : 'Daftar perusahaan'}
                                    </LandingButton>
                                    <LandingButton href="#alur" variant="secondary" size="responsive">
                                        Lihat alur kerja
                                    </LandingButton>
                                </div>
                            </section>
                        </main>

                        <LandingFooter appName={appName} logoUrl={logoUrl} tagline={aplikasi?.tagline} />
                    </div>
                </SmoothScrollProvider>
            </MotionConfig>
        </>
    );
}
