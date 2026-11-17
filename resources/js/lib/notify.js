import { toast } from 'sonner';
import { playSound } from '@/lib/sound';

export const notify = {
    success(message, options = {}) {
        playSound('success');
        return toast.success(message, {
            closeButton: true,
            ...options,
        });
    },
    error(message, options = {}) {
        playSound('failure');
        return toast.error(message, {
            closeButton: true,
            ...options,
        });
    },
    info(message, options = {}) {
        return toast.info(message, {
            closeButton: true,
            ...options,
        });
    },
    warning(message, options = {}) {
        return toast.warning(message, {
            closeButton: true,
            ...options,
        });
    },
    validationFailed() {
        playSound('failure');
    },
};
