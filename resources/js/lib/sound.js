/**
 * Audio playback utility for success and failure events.
 */
const SOURCES = {
    success: '/assets/sounds/success.mp3',
    failure: '/assets/sounds/failure.mp3',
};

const cache = {};

export function playSound(type) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const audio = (cache[type] ??= new Audio(SOURCES[type] ?? SOURCES.failure));
        audio.currentTime = 0;
        audio.play().catch(() => {});
    } catch {
        // Safe fall-through on browsers blocking un-interacted audio
    }
}
