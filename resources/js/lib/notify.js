import { toast } from 'sonner';
import { playSound } from '@/lib/sound';

export const notify = {
    success(message) {
        playSound('success');
        toast.success(message);
    },
    error(message) {
        playSound('failure');
        toast.error(message);
    },
    validationFailed() {
        playSound('failure');
    },
};
