import { cn } from '@/lib/utils';
import { Reveal } from './Reveal';

export default function SectionHeading({ eyebrow, title, description, className }) {
    return (
        <Reveal className={cn('landing-container flex flex-col items-center text-center', className)}>
            {eyebrow && <p className="mb-4 type-eyebrow text-warm-60">{eyebrow}</p>}
            <h2 className="max-w-5xl type-heading-m whitespace-pre-line">{title}</h2>
            {description && <p className="mt-4 max-w-[660px] type-body-m text-warm-60 desk:mt-6">{description}</p>}
        </Reveal>
    );
}
