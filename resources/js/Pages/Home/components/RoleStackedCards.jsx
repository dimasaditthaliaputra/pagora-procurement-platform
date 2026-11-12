import { cn } from '@/lib/utils';
import { roleCards } from '@/data/landingContent';
import LandingButton from './LandingButton';
import LandingImage from './LandingImage';

const STACK_OFFSETS = ['desk:top-[144px]', 'desk:top-[164px]', 'desk:top-[184px]', 'desk:top-[204px]'];
const MOBILE_STACK_OFFSETS = ['top-[90px]', 'top-[102px]', 'top-[114px]', 'top-[126px]'];

/**
 * Role cards that stick and pile up while scrolling (DESIGN.md §6.9).
 * Each following card covers the previous one, leaving a small edge visible.
 */
export default function RoleStackedCards() {
    return (
        <ol className="landing-container mt-10 flex flex-col gap-[50px] desk:mt-14">
            {roleCards.map((card, index) => (
                <li key={card.eyebrow} className={cn('sticky', MOBILE_STACK_OFFSETS[index], STACK_OFFSETS[index])}>
                    <article
                        className={cn(
                            'grid grid-cols-[minmax(0,1fr)] overflow-hidden rounded-small bg-white desk:h-[400px] desk:grid-cols-[minmax(0,1fr)_minmax(0,58%)] desk:rounded-medium',
                            index > 0 ? 'shadow-soft-stack' : 'shadow-soft-sm',
                        )}
                    >
                        <div className="flex flex-col p-6 desk:p-10">
                            <p className="type-eyebrow text-warm-60">{card.eyebrow}</p>
                            <h3 className="mt-4 type-heading-xs desk:mt-auto">{card.title}</h3>
                            <p className="mt-3 max-w-[24rem] type-body-s text-warm-60">{card.description}</p>
                            <LandingButton href={card.link.href} variant="tertiary" className="mt-6 self-start">
                                {card.link.label}
                            </LandingButton>
                        </div>
                        <div className={cn('relative h-[260px] overflow-hidden tab:h-[320px] desk:h-full', card.color)}>
                            <LandingImage name={card.image} />
                        </div>
                    </article>
                </li>
            ))}
        </ol>
    );
}
