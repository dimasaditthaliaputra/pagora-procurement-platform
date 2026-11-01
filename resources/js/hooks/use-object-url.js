import { useEffect, useState } from 'react';

/**
 * Buat object URL untuk preview File/Blob dan cabut otomatis saat file berganti atau komponen dilepas.
 */
export function useObjectUrl(file) {
    const [url, setUrl] = useState(null);

    useEffect(() => {
        if (!(file instanceof Blob)) {
            setUrl(null);
            return undefined;
        }

        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return url;
}
