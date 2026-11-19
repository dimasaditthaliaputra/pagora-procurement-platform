import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { Input } from '@/Components/ui/input';
import { formatRupiahInput, parseRupiahInput } from '@/lib/format';

// Characters that carry meaning in the visible text (digits and the decimal comma); dots are only grouping.
const significant = (text) => text.replace(/[^\d,]/g, '');

/**
 * Rupiah input with live thousand separators ("15.000"). `value` / `onValueChange` use a plain
 * numeric string ("15000", "15000.5"), so it drops into `useForm` without changing validation.
 */
export function CurrencyInput({ value, onValueChange, maxDecimals = 2, className, ...props }) {
    const [text, setText] = useState(() => formatRupiahInput(value));
    const inputRef = useRef(null);
    const caret = useRef(null);

    // Follow outside changes (suggestion chips, reset) without fighting what the user is typing.
    useEffect(() => {
        if (parseRupiahInput(text, maxDecimals).raw !== String(value ?? '')) {
            setText(formatRupiahInput(value));
        }
    }, [value]);

    // Re-place the caret after the same number of digits it followed before reformatting.
    useLayoutEffect(() => {
        if (caret.current === null || !inputRef.current) return;
        let remaining = caret.current;
        let position = 0;
        while (position < text.length && remaining > 0) {
            if (/[\d,]/.test(text[position])) remaining -= 1;
            position += 1;
        }
        inputRef.current.setSelectionRange(position, position);
        caret.current = null;
    }, [text]);

    const handleChange = (event) => {
        const typed = event.target.value;
        const before = significant(typed.slice(0, event.target.selectionStart ?? typed.length)).length;
        const { raw, trailingComma } = parseRupiahInput(typed, maxDecimals);

        caret.current = before;
        setText(formatRupiahInput(raw, { trailingComma }));
        onValueChange(raw);
    };

    return (
        <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-sm text-muted-foreground">Rp</span>
            <Input
                ref={inputRef}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                value={text}
                onChange={handleChange}
                className={cn('pl-9 tabular-nums', className)}
                {...props}
            />
        </div>
    );
}
