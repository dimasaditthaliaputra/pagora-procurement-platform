import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { notify } from '@/lib/notify';

export function FlashHandler() {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            notify.success(flash.success);
        }
    }, [flash?.success]);

    useEffect(() => {
        if (flash?.error) {
            notify.error(flash.error);
        }
    }, [flash?.error]);

    return null;
}
