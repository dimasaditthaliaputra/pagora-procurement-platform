import { useState } from 'react';
import { cn } from '@/lib/utils';
import { landingImages } from '@/data/landingImages';

/**
 * Lazy-loaded landing photo that covers its container.
 * The parent keeps its color/icon styling underneath, so if the file fails to load
 * the image simply removes itself and that styling remains as the fallback.
 */
export default function LandingImage({ name, className, isDecorative = false, loading = 'lazy' }) {
    const [hasFailed, setHasFailed] = useState(false);
    const image = landingImages[name];

    if (!image || hasFailed) {
        return null;
    }

    return (
        <img
            src={image.src}
            alt={isDecorative ? '' : image.alt}
            loading={loading}
            decoding="async"
            draggable={false}
            onError={() => setHasFailed(true)}
            className={cn('absolute inset-0 size-full object-cover', className)}
        />
    );
}
