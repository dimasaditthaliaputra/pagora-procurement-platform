import { cn } from '@/lib/utils';
import { safeguards } from '@/data/landingContent';
import LandingImage from './LandingImage';
import { Stagger, StaggerItem } from './Reveal';

/**
 * Four safeguard cards with a photo header. On hover the photo zooms slightly and the card lifts,
 * standing in for the hover-to-play video cards in DESIGN.md §6.4.
 */
export default function SafeguardCards() {
    return (
        <Stagger className="landing-container mt-10 grid gap-4 tab:grid-cols-2 desk:mt-14 desk:grid-cols-4 desk:gap-5">
            {safeguards.map((item) => (
                <StaggerItem
                    key={item.title}
                    className="group/card flex flex-col overflow-hidden rounded-small bg-white shadow-soft-sm transition-shadow duration-500 hover:shadow-soft-md desk:min-h-[380px] desk:rounded-medium"
                >
                    <div className={cn('relative grid h-[180px] place-items-center overflow-hidden', item.color)} aria-hidden="true">
                        <item.icon className="size-10 text-brand-offblack/40" />
                        <LandingImage
                            name={item.image}
                            isDecorative
                            className="transition-transform duration-700 ease-out-quick group-hover/card:scale-[1.06]"
                        />
                        <span className="absolute inset-0 bg-gradient-to-t from-brand-offblack/35 via-transparent to-transparent" />
                        <span
                            className={cn(
                                'absolute bottom-4 left-4 grid size-12 place-items-center rounded-full text-brand-offblack shadow-soft-sm transition-transform duration-500 ease-out-quick group-hover/card:-rotate-6',
                                item.color,
                            )}
                        >
                            <item.icon className="size-5" />
                        </span>
                    </div>
                    <div className="flex flex-1 flex-col p-6">
                        <h3 className="type-heading-xs">{item.title}</h3>
                        <p className="mt-3 type-body-s text-warm-60">{item.description}</p>
                    </div>
                </StaggerItem>
            ))}
        </Stagger>
    );
}
