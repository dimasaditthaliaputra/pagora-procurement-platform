import { useCallback, useEffect, useState } from 'react';

export function useCountdown(initial = 0) {
    const [seconds, setSeconds] = useState(initial);

    useEffect(() => {
        if (seconds <= 0) return undefined;

        const timer = setTimeout(() => setSeconds((s) => s - 1), 1000);

        return () => clearTimeout(timer);
    }, [seconds]);

    const start = useCallback((value) => setSeconds(value), []);

    return { seconds, start };
}
