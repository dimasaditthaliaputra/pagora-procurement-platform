import { useEffect, useMemo, useRef, useState } from 'react';
import { formatDate, formatRupiah } from '@/lib/format';

const HEIGHT = 300;
const MARGIN = { top: 16, right: 16, bottom: 28, left: 64 };
const DAY_MS = 24 * 60 * 60 * 1000;

// Categorical slots 1–2 of the dataviz reference palette, stepped per mode (validated light & dark).
const SERIES = {
    history: { stroke: 'stroke-[#2a78d6] dark:stroke-[#3987e5]', fill: 'fill-[#2a78d6] dark:fill-[#3987e5]', key: 'bg-[#2a78d6] dark:bg-[#3987e5]' },
    prediction: { stroke: 'stroke-[#eb6834] dark:stroke-[#d95926]', fill: 'fill-[#eb6834] dark:fill-[#d95926]', key: 'bg-[#eb6834] dark:bg-[#d95926]' },
};

const compactRupiah = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });

function toTime(date) {
    return new Date(`${date}T00:00:00`).getTime();
}

function niceTicks(min, max, count = 5) {
    const span = max - min || Math.abs(max) || 1;
    const rawStep = span / count;
    const magnitude = 10 ** Math.floor(Math.log10(rawStep));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((s) => s >= rawStep) ?? 10 * magnitude;
    const start = Math.floor(min / step) * step;
    const end = Math.ceil(max / step) * step;
    const ticks = [];
    for (let value = start; value <= end + step / 2; value += step) ticks.push(value);
    return ticks;
}

function useWidth() {
    const ref = useRef(null);
    const [width, setWidth] = useState(0);

    useEffect(() => {
        if (!ref.current) return undefined;
        const observer = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width));
        observer.observe(ref.current);
        return () => observer.disconnect();
    }, []);

    return [ref, width];
}

export function PriceTrendChart({ history, predictions, ceiling, unit, historyLabel }) {
    const [containerRef, width] = useWidth();
    const [hoverTime, setHoverTime] = useState(null);

    const historyPoints = useMemo(() => history.map((p) => ({ t: toTime(p.date), date: p.date, value: p.price })), [history]);
    const predictionPoints = useMemo(
        () => predictions.map((p) => ({ t: toTime(p.prediction_date), date: p.prediction_date, value: p.predicted_price })),
        [predictions],
    );

    const scales = useMemo(() => {
        const all = [...historyPoints, ...predictionPoints];
        if (all.length === 0 || width === 0) return null;

        const times = all.map((p) => p.t);
        const values = all.map((p) => p.value).concat(ceiling ? [ceiling] : []);
        const minT = Math.min(...times);
        const maxT = Math.max(...times);
        const ticks = niceTicks(Math.min(...values) * 0.98, Math.max(...values) * 1.02);
        const minV = ticks[0];
        const maxV = ticks[ticks.length - 1];
        const innerW = Math.max(width - MARGIN.left - MARGIN.right, 1);
        const innerH = HEIGHT - MARGIN.top - MARGIN.bottom;

        return {
            x: (t) => MARGIN.left + (maxT === minT ? innerW / 2 : ((t - minT) / (maxT - minT)) * innerW),
            y: (v) => MARGIN.top + innerH - ((v - minV) / (maxV - minV || 1)) * innerH,
            invertX: (px) => minT + ((px - MARGIN.left) / innerW) * (maxT - minT),
            ticks,
            minT,
            maxT,
            innerW,
        };
    }, [historyPoints, predictionPoints, ceiling, width]);

    const xTicks = useMemo(() => {
        if (!scales) return [];
        const count = width < 480 ? 3 : 6;
        return Array.from({ length: count }, (_, i) => scales.minT + ((scales.maxT - scales.minT) * i) / (count - 1));
    }, [scales, width]);

    const toPath = (points) => points.map((p, i) => `${i === 0 ? 'M' : 'L'}${scales.x(p.t).toFixed(1)},${scales.y(p.value).toFixed(1)}`).join(' ');

    // Forecast starts where the BI series ends so the two read as one continuing trend.
    const lastHistory = historyPoints[historyPoints.length - 1];
    const predictionPath = lastHistory && predictionPoints.length ? [lastHistory, ...predictionPoints] : predictionPoints;

    const nearest = (points, t) => points.reduce((best, p) => (!best || Math.abs(p.t - t) < Math.abs(best.t - t) ? p : best), null);

    const hover = useMemo(() => {
        if (hoverTime === null || !scales) return null;
        const candidates = [...historyPoints, ...predictionPoints];
        const anchor = nearest(candidates, hoverTime);
        if (!anchor) return null;
        const pick = (points) => {
            const point = nearest(points, anchor.t);
            return point && Math.abs(point.t - anchor.t) < DAY_MS / 2 ? point : null;
        };
        return { anchor, history: pick(historyPoints), prediction: pick(predictionPoints) };
    }, [hoverTime, historyPoints, predictionPoints, scales]);

    const handlePointer = (event) => {
        if (!scales) return;
        const rect = event.currentTarget.getBoundingClientRect();
        setHoverTime(scales.invertX(event.clientX - rect.left));
    };

    const lastPrediction = predictionPoints[predictionPoints.length - 1];
    const tooltipLeft = hover ? Math.min(Math.max(scales.x(hover.anchor.t) + 12, MARGIN.left), width - 200) : 0;

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground" aria-hidden="true">
                <LegendKey className={SERIES.history.key} label={historyLabel} />
                <LegendKey className={SERIES.prediction.key} label="Prediksi" />
                {ceiling ? <LegendKey dashed label="Harga Pagu" /> : null}
            </div>

            <div ref={containerRef} className="relative h-[300px] w-full">
                {scales && (
                    <svg
                        width={width}
                        height={HEIGHT}
                        role="img"
                        aria-label={`Grafik tren harga dan prediksi per ${unit}`}
                        className="touch-none select-none"
                        onPointerMove={handlePointer}
                        onPointerDown={handlePointer}
                        onPointerLeave={() => setHoverTime(null)}
                    >
                        {scales.ticks.map((tick) => (
                            <g key={tick}>
                                <line x1={MARGIN.left} x2={width - MARGIN.right} y1={scales.y(tick)} y2={scales.y(tick)} className="stroke-border" strokeWidth={1} />
                                <text x={MARGIN.left - 8} y={scales.y(tick)} dy="0.32em" textAnchor="end" className="fill-muted-foreground text-[11px] tabular-nums">
                                    {compactRupiah.format(tick)}
                                </text>
                            </g>
                        ))}

                        {xTicks.map((t) => (
                            <text key={t} x={scales.x(t)} y={HEIGHT - 8} textAnchor="middle" className="fill-muted-foreground text-[11px]">
                                {formatDate(new Date(t))}
                            </text>
                        ))}

                        {ceiling ? (
                            <g>
                                <line
                                    x1={MARGIN.left}
                                    x2={width - MARGIN.right}
                                    y1={scales.y(ceiling)}
                                    y2={scales.y(ceiling)}
                                    className="stroke-foreground/60"
                                    strokeWidth={1.5}
                                    strokeDasharray="6 4"
                                />
                                <text x={width - MARGIN.right} y={scales.y(ceiling) - 6} textAnchor="end" className="fill-foreground text-[11px] font-medium">
                                    Harga Pagu {formatRupiah(ceiling)}
                                </text>
                            </g>
                        ) : null}

                        {historyPoints.length > 0 && (
                            <path d={toPath(historyPoints)} fill="none" strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" className={SERIES.history.stroke} />
                        )}
                        {predictionPath.length > 0 && (
                            <path d={toPath(predictionPath)} fill="none" strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" className={SERIES.prediction.stroke} />
                        )}

                        {lastPrediction && (
                            <circle cx={scales.x(lastPrediction.t)} cy={scales.y(lastPrediction.value)} r={4} strokeWidth={2} className={`${SERIES.prediction.fill} stroke-card`} />
                        )}

                        {hover && (
                            <g pointerEvents="none">
                                <line x1={scales.x(hover.anchor.t)} x2={scales.x(hover.anchor.t)} y1={MARGIN.top} y2={HEIGHT - MARGIN.bottom} className="stroke-muted-foreground/50" strokeWidth={1} />
                                {hover.history && (
                                    <circle cx={scales.x(hover.history.t)} cy={scales.y(hover.history.value)} r={4} strokeWidth={2} className={`${SERIES.history.fill} stroke-card`} />
                                )}
                                {hover.prediction && (
                                    <circle cx={scales.x(hover.prediction.t)} cy={scales.y(hover.prediction.value)} r={4} strokeWidth={2} className={`${SERIES.prediction.fill} stroke-card`} />
                                )}
                            </g>
                        )}
                    </svg>
                )}

                {hover && (
                    <div
                        className="pointer-events-none absolute top-2 w-48 rounded-lg border border-border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
                        style={{ left: tooltipLeft }}
                    >
                        <p className="mb-1 font-medium">{formatDate(hover.anchor.date)}</p>
                        {hover.history && <TooltipRow keyClass={SERIES.history.key} label="Harga" value={hover.history.value} />}
                        {hover.prediction && <TooltipRow keyClass={SERIES.prediction.key} label="Prediksi" value={hover.prediction.value} />}
                        {ceiling ? <TooltipRow dashed label="Harga Pagu" value={ceiling} /> : null}
                    </div>
                )}
            </div>
        </div>
    );
}

function LegendKey({ className, label, dashed = false }) {
    return (
        <span className="inline-flex items-center gap-2">
            {dashed ? (
                <span className="w-4 border-t-[1.5px] border-dashed border-foreground/60" />
            ) : (
                <span className={`h-0.5 w-4 rounded-full ${className}`} />
            )}
            {label}
        </span>
    );
}

function TooltipRow({ keyClass, label, value, dashed = false }) {
    return (
        <div className="flex items-center justify-between gap-3 py-0.5">
            <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                {dashed ? <span className="w-3 border-t-[1.5px] border-dashed border-foreground/60" /> : <span className={`h-0.5 w-3 rounded-full ${keyClass}`} />}
                {label}
            </span>
            <span className="font-semibold tabular-nums">{formatRupiah(value)}</span>
        </div>
    );
}
