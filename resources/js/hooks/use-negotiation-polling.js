import { useEffect, useRef, useState } from 'react';

const MAX_BACKOFF_FACTOR = 8;

/**
 * Polls the negotiation updates endpoint (NFR-12: new messages within 5 s).
 *
 * - Sends only the newest message uuid it has (`after`), so each poll returns just the new messages.
 * - Pauses while the tab is hidden: saves dyno requests and lets the 30-minute idle session
 *   timeout (NFR-03) apply to forgotten background tabs.
 * - Backs off exponentially after failures and reports connectivity for the UI.
 */
export function useNegotiationPolling({ url, intervalMs, enabled, getCursor, onUpdate }) {
    const [connected, setConnected] = useState(true);
    const onUpdateRef = useRef(onUpdate);
    const getCursorRef = useRef(getCursor);

    useEffect(() => {
        onUpdateRef.current = onUpdate;
        getCursorRef.current = getCursor;
    });

    useEffect(() => {
        if (!enabled) return undefined;

        let timer = null;
        let controller = null;
        let failures = 0;
        let stopped = false;

        const schedule = () => {
            if (stopped) return;
            const factor = Math.min(2 ** failures, MAX_BACKOFF_FACTOR);
            timer = setTimeout(tick, intervalMs * factor);
        };

        const tick = async () => {
            if (document.visibilityState !== 'visible') {
                schedule();
                return;
            }

            controller = new AbortController();
            const cursor = getCursorRef.current();
            const query = cursor ? `?after=${encodeURIComponent(cursor)}` : '';

            try {
                const response = await fetch(`${url}${query}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });

                // Session expired or access revoked: stop instead of hammering the server.
                if ([401, 403, 404, 419].includes(response.status)) {
                    stopped = true;
                    setConnected(false);
                    return;
                }

                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const body = await response.json();
                failures = 0;
                setConnected(true);
                onUpdateRef.current(body.data);
            } catch (error) {
                if (error.name === 'AbortError') return;
                failures += 1;
                setConnected(false);
            }

            schedule();
        };

        const onVisible = () => {
            if (document.visibilityState === 'visible' && !stopped) {
                clearTimeout(timer);
                tick();
            }
        };

        schedule();
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            stopped = true;
            clearTimeout(timer);
            controller?.abort();
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [url, intervalMs, enabled]);

    return { connected };
}
