import { useState } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';

export function PasswordInput({ id, value, onChange, error, disabled, autoComplete, className }) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input
                id={id}
                type={visible ? 'text' : 'password'}
                value={value}
                onChange={onChange}
                autoComplete={autoComplete}
                aria-invalid={!!error}
                aria-describedby={error ? `${id}-error` : undefined}
                className={cn('pr-10', error && 'border-destructive focus-visible:ring-destructive', className)}
                disabled={disabled}
            />
            <button
                type="button"
                onClick={() => setVisible(!visible)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground focus:outline-none cursor-pointer"
                tabIndex={-1}
                aria-label={visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
            >
                {visible ? <Eye className="size-4" /> : <EyeOff className="size-4" />}
            </button>
        </div>
    );
}
