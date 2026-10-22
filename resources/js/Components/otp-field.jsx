import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import { AlertCircle } from 'lucide-react';

export const OTP_LENGTH = 6;

export function OtpField({ id = 'otp', label = 'Kode OTP', value, onChange, error, disabled, autoFocus }) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id} className={cn('text-sm font-medium', error && 'text-destructive')}>
                {label} <span className="text-destructive">*</span>
            </Label>
            <Input
                id={id}
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                placeholder="______"
                value={value}
                disabled={disabled}
                autoFocus={autoFocus}
                onChange={(e) => onChange(e.target.value.replace(/\D/g, '').slice(0, OTP_LENGTH))}
                aria-invalid={!!error}
                className={cn(
                    'h-11 w-full text-center text-lg font-semibold tracking-[0.5em]',
                    error && 'border-destructive focus-visible:ring-destructive'
                )}
            />
            {error && (
                <p className="text-xs font-medium text-destructive flex items-center gap-1.5 mt-1">
                    <AlertCircle className="size-3.5 shrink-0" />
                    <span>{error}</span>
                </p>
            )}
        </div>
    );
}
