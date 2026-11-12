import { commodities } from '@/data/landingContent';
import VelocityMarquee from './VelocityMarquee';

function CommodityList() {
    return (
        <ul className="flex shrink-0 items-center gap-10 pr-10">
            {commodities.map((commodity) => (
                <li key={commodity} className="flex items-center gap-10 type-heading-xs whitespace-nowrap text-brand-offblack">
                    {commodity}
                    <span className="size-1.5 rounded-full bg-brand-offblack/40" aria-hidden="true" />
                </li>
            ))}
        </ul>
    );
}

/** Strip of tracked commodities. Drifts slowly and speeds up / leans with scroll velocity. */
export default function CommodityMarquee() {
    return (
        <section aria-label="Komoditas yang dipantau" className="relative z-10 bg-brand-lime py-5">
            <div className="flex flex-col gap-3 desk:flex-row desk:items-center desk:gap-0">
                <p className="shrink-0 px-[33px] type-body-xs text-brand-offblack/70 tab:px-[34px] desk:w-[260px] desk:pr-8 desk:pl-12 wide:w-[calc(50vw-650px+212px)] wide:pl-[calc(50vw-650px)]">
                    Harga acuan dari data pangan Bank Indonesia
                </p>
                <VelocityMarquee
                    baseVelocity={-1.6}
                    className="flex-1 [mask-image:linear-gradient(90deg,transparent,#000_8%,#000_92%,transparent)]"
                >
                    <CommodityList />
                </VelocityMarquee>
            </div>
        </section>
    );
}
