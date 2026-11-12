import { useLayoutEffect, useRef } from 'react';
import { PanelLeft, Search, Signal, Wifi } from 'lucide-react';
import { cn } from '@/lib/utils';
import { mainNavItems } from '@/Components/AppSidebar';
import DashboardOverview from '@/Components/DashboardOverview';

const LAPTOP_SCREEN = { width: 1280, viewportClassName: 'aspect-[1280/800]', canvasClassName: 'h-[800px] w-[1280px]' };
const PHONE_SCREEN = { width: 390, viewportClassName: 'aspect-[390/844]', canvasClassName: 'h-[844px] w-[390px]' };
const PREVIEW_USER = 'Tim Purchasing';

/**
 * Renders `children` at a real screen size and scales it to the available width,
 * so the app UI keeps its exact layout and stays sharp on every breakpoint.
 */
function ScaledScreen({ design, className, children }) {
    const viewportRef = useRef(null);
    const canvasRef = useRef(null);

    useLayoutEffect(() => {
        const viewport = viewportRef.current;
        const applyScale = () => {
            canvasRef.current.style.transform = `scale(${viewport.clientWidth / design.width})`;
        };

        applyScale();
        const observer = new ResizeObserver(applyScale);
        observer.observe(viewport);

        return () => observer.disconnect();
    }, [design.width]);

    return (
        <div ref={viewportRef} className={cn('relative w-full overflow-hidden', design.viewportClassName, className)}>
            <div ref={canvasRef} className={cn('app-screen-light absolute top-0 left-0 origin-top-left', design.canvasClassName)}>
                {children}
            </div>
        </div>
    );
}

/** Header bar as rendered by DashboardLayout: sidebar trigger, separator and breadcrumb. */
function AppHeader() {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-border/40 px-4">
            <PanelLeft className="size-4 text-foreground" />
            <span className="mx-2 h-4 w-px bg-border" />
            <span className="text-sm font-normal text-foreground">Dashboard</span>
        </header>
    );
}

/** Desktop sidebar mirroring AppSidebar: logo block, menu search and the "Menu Utama" group. */
function AppSidebarPreview({ appName, logoUrl }) {
    return (
        <aside className="flex w-64 shrink-0 flex-col border-r border-zinc-800/80 bg-zinc-950 text-zinc-100">
            <div className="border-b border-zinc-800/80 px-4 py-4">
                <div className="flex items-center gap-2.5">
                    <span className="flex aspect-square size-9 items-center justify-center rounded-xl border border-white/20 bg-white p-1.5">
                        <img src={logoUrl} alt="" className="size-full object-contain" />
                    </span>
                    <span className="truncate text-base font-bold tracking-tight text-white">{appName}</span>
                </div>
                <div className="relative mt-3">
                    <Search className="absolute top-2.5 left-3 size-4 text-zinc-500" />
                    <div className="flex h-9 items-center rounded-lg border border-zinc-800 bg-zinc-900/90 pl-9 text-xs text-zinc-500">
                        Cari menu...
                    </div>
                </div>
            </div>
            <div className="px-3 py-3">
                <p className="px-2 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">Menu Utama</p>
                <ul className="mt-2 flex flex-col gap-1">
                    {mainNavItems.map((item, index) => (
                        <li
                            key={item.title}
                            className={cn(
                                'flex h-10 items-center justify-between gap-3 rounded-lg px-3 text-sm',
                                index === 0 ? 'border border-white/10 bg-white/12 font-semibold text-white' : 'text-zinc-400',
                            )}
                        >
                            <span className="flex min-w-0 items-center gap-3">
                                <item.icon className={cn('size-5 shrink-0', index === 0 ? 'text-white' : 'text-zinc-400')} />
                                <span className="truncate font-medium">{item.title}</span>
                            </span>
                            {item.badge && (
                                <span className="rounded-md border border-zinc-700 bg-zinc-800 px-1.5 text-[10px] font-semibold text-zinc-200">
                                    {item.badge}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        </aside>
    );
}

function DesktopDashboardScreen({ appName, logoUrl }) {
    return (
        <div className="flex h-full bg-background text-foreground">
            <AppSidebarPreview appName={appName} logoUrl={logoUrl} />
            <div className="flex min-w-0 flex-1 flex-col">
                <AppHeader />
                <div className="flex-1 overflow-hidden p-4">
                    <DashboardOverview userName={PREVIEW_USER} layout="wide" />
                </div>
            </div>
        </div>
    );
}

function MobileDashboardScreen() {
    return (
        <div className="flex h-full flex-col bg-background text-foreground">
            <div className="flex items-center justify-between px-7 pt-4 pb-1 text-[15px] font-semibold">
                <span>09.41</span>
                <span className="flex items-center gap-1.5">
                    <Signal className="size-4" />
                    <Wifi className="size-4" />
                    <span className="h-3 w-6 rounded-[4px] border border-foreground p-[1.5px]">
                        <span className="block h-full w-3/4 rounded-[2px] bg-foreground" />
                    </span>
                </span>
            </div>
            <div className="mt-6">
                <AppHeader />
            </div>
            <div className="flex-1 overflow-hidden p-4">
                <DashboardOverview userName={PREVIEW_USER} layout="compact" />
            </div>
        </div>
    );
}

/** Laptop with a thin bezel, camera notch, hinge base and a soft contact shadow. */
export function LaptopFrame({ appName, logoUrl, className }) {
    return (
        <div className={cn('relative', className)}>
            <div className="relative rounded-t-[18px] bg-[#0f0f0c] p-[1.4%] pb-[2.2%] shadow-[0_0_0_1px_#2a2a24,inset_0_0_0_1px_#3a3a33,0_24px_48px_-24px_#14140f33]">
                <span className="absolute top-[0.55%] left-1/2 size-[5px] -translate-x-1/2 rounded-full bg-[#2c2c27]" aria-hidden="true" />
                <ScaledScreen design={LAPTOP_SCREEN} className="rounded-[6px]">
                    <DesktopDashboardScreen appName={appName} logoUrl={logoUrl} />
                </ScaledScreen>
            </div>
            <span
                aria-hidden="true"
                className="absolute inset-x-[4%] -bottom-5 h-10 rounded-[50%] bg-brand-offblack/20 blur-2xl desk:-bottom-7 desk:h-14"
            />
            <div className="relative -mx-[7%] h-[12px] rounded-b-[16px] bg-gradient-to-b from-[#e4e4de] via-[#c9c9c2] to-[#8f8f88] shadow-soft-md desk:h-[16px]">
                <span className="absolute top-0 left-1/2 h-[5px] w-[16%] -translate-x-1/2 rounded-b-[8px] bg-[#a9a9a2]" aria-hidden="true" />
            </div>
        </div>
    );
}

/** Smartphone with side buttons and a dynamic-island cutout, showing the mobile dashboard. */
export function PhoneFrame({ className }) {
    return (
        <div className={cn('relative', className)}>
            <span className="absolute top-[22%] -left-[3px] h-[8%] w-[3px] rounded-l bg-[#2a2a24]" aria-hidden="true" />
            <span className="absolute top-[32%] -left-[3px] h-[8%] w-[3px] rounded-l bg-[#2a2a24]" aria-hidden="true" />
            <span className="absolute top-[26%] -right-[3px] h-[12%] w-[3px] rounded-r bg-[#2a2a24]" aria-hidden="true" />
            <div className="rounded-[18%/8.5%] bg-[#0f0f0c] p-[3.5%] shadow-[0_0_0_1px_#2a2a24,inset_0_0_0_1px_#3a3a33,0_2px_4px_#14140f0f,0_20px_40px_-12px_#14140f33]">
                <div className="relative overflow-hidden rounded-[15%/7%]">
                    <span
                        className="absolute top-[1.6%] left-1/2 z-10 h-[4%] w-[30%] -translate-x-1/2 rounded-full bg-[#0f0f0c]"
                        aria-hidden="true"
                    />
                    <ScaledScreen design={PHONE_SCREEN}>
                        <MobileDashboardScreen />
                    </ScaledScreen>
                </div>
            </div>
        </div>
    );
}
