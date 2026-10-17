import { Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';

export function LoadingButton({
    loading = false,
    loadingText,
    children,
    disabled,
    ...props
}) {
    return (
        <Button disabled={disabled || loading} aria-busy={loading} {...props}>
            {loading && <Loader2 className="mr-2 size-4 animate-spin" />}
            {loading && loadingText ? loadingText : children}
        </Button>
    );
}
