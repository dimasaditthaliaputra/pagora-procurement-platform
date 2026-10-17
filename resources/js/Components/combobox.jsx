import * as React from 'react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';

export function Combobox({
    options = [],
    value,
    onChange,
    placeholder = 'Pilih...',
    searchPlaceholder = 'Cari...',
    emptyMessage = 'Tidak ditemukan.',
    error,
    disabled = false,
    className,
}) {
    const [open, setOpen] = React.useState(false);

    // Normalize options if passed as array of strings
    const normalizedOptions = React.useMemo(() => {
        return options.map((opt) =>
            typeof opt === 'string' ? { value: opt, label: opt } : opt
        );
    }, [options]);

    const selected = normalizedOptions.find((o) => o.value === value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={Boolean(error)}
                    disabled={disabled}
                    className={cn(
                        'w-full justify-between font-normal h-9',
                        !selected && 'text-muted-foreground',
                        error && 'border-destructive focus-visible:ring-destructive',
                        className
                    )}
                >
                    <span className="truncate">{selected?.label ?? placeholder}</span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[var(--radix-popover-trigger-width)] min-w-[200px] p-0"
                align="start"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} className="h-9" />
                    <CommandList>
                        <CommandEmpty>{emptyMessage}</CommandEmpty>
                        <CommandGroup>
                            {normalizedOptions.map((o) => (
                                <CommandItem
                                    key={o.value}
                                    value={o.label}
                                    onSelect={() => {
                                        onChange(o.value === value ? '' : o.value);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            value === o.value ? 'opacity-100' : 'opacity-0'
                                        )}
                                    />
                                    {o.label}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
