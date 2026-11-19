import { useEffect, useRef, useState } from 'react';
import { Progress } from '@/Components/ui/progress';

const TICK_MS = 200;
const CEILING = 90;

const STAGES = [
    { until: 45, label: 'Mengambil harga terbaru dari Bank Indonesia...' },
    { until: 80, label: 'Menghitung prediksi harga...' },
    { until: 100, label: 'Menyimpan Harga Pagu...' },
];

/**
 * The refresh is a single request, so progress is estimated: it eases toward 90% while the
 * request runs, then jumps to 100% when the server answers.
 */
export function useRefreshProgress() {
    const [value, setValue] = useState(null);
    const timer = useRef(null);
    const hideTimer = useRef(null);

    const clear = () => {
        clearInterval(timer.current);
        clearTimeout(hideTimer.current);
    };

    useEffect(() => clear, []);

    const start = () => {
        clear();
        setValue(5);
        timer.current = setInterval(() => {
            setValue((current) => (current === null ? null : current + (CEILING - current) * 0.08));
        }, TICK_MS);
    };

    const finish = () => {
        clearInterval(timer.current);
        setValue(100);
        hideTimer.current = setTimeout(() => setValue(null), 600);
    };

    return { value, start, finish };
}

export function RefreshProgress({ value, commodityName }) {
    if (value === null) return null;

    const done = value >= 100;
    const stage = done ? 'Selesai' : STAGES.find((item) => value < item.until)?.label;

    return (
        <div className="rounded-lg border border-border bg-card px-4 py-3 shadow-xs" aria-live="polite">
            <div className="mb-2 flex items-center justify-between gap-3 text-xs">
                <span className="font-medium text-foreground">
                    Memperbarui {commodityName}
                </span>
                <span className="tabular-nums text-muted-foreground">{Math.round(value)}%</span>
            </div>
            <Progress value={value} aria-label={`Memperbarui data ${commodityName}`} />
            <p className="mt-2 text-xs text-muted-foreground">{stage}</p>
        </div>
    );
}
