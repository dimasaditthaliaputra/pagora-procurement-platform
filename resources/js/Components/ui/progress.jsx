import { cn } from '@/lib/utils';

/**
 * Progress bar sederhana. `value` 0-100; tanpa `value` tampil animasi indeterminate.
 */
function Progress({ className, value, ...props }) {
    const determinate = typeof value === 'number';
    const clamped = determinate ? Math.min(100, Math.max(0, value)) : 0;

    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={determinate ? clamped : undefined}
            data-slot="progress"
            className={cn('relative h-1.5 w-full overflow-hidden rounded-full bg-primary/15', className)}
            {...props}
        >
            <div
                className={cn(
                    'h-full rounded-full bg-primary transition-[width] duration-200 ease-out',
                    !determinate && 'w-1/3 animate-pulse'
                )}
                style={determinate ? { width: `${clamped}%` } : undefined}
            />
        </div>
    );
}

export { Progress };
