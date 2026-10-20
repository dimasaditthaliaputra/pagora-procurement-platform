import React, { useState, useEffect } from 'react';
import { useTheme } from 'next-themes';
import { Sun, Moon, Monitor, Check } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export function ThemeToggle({ className, variant = 'ghost', size = 'icon', ...props }) {
    const { theme, setTheme } = useTheme();
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    if (!mounted) {
        return (
            <Button
                variant={variant}
                size={size}
                className={cn('size-8 rounded-full', className)}
                disabled
                {...props}
            >
                <Sun className="size-4 opacity-50" />
                <span className="sr-only">Toggle theme</span>
            </Button>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant={variant}
                    size={size}
                    className={cn(
                        'size-8 rounded-full transition-colors relative',
                        className
                    )}
                    title="Ubah tema tampilan"
                    {...props}
                >
                    <Sun className="size-4 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0 text-amber-500" />
                    <Moon className="absolute size-4 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100 text-sky-400" />
                    <span className="sr-only">Ubah tema</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40 rounded-xl p-1.5 shadow-lg border border-border/70">
                <DropdownMenuItem
                    onClick={() => setTheme('light')}
                    className="flex items-center justify-between cursor-pointer rounded-lg px-2.5 py-1.5 text-xs font-medium"
                >
                    <div className="flex items-center gap-2">
                        <Sun className="size-4 text-amber-500" />
                        <span>Terang</span>
                    </div>
                    {theme === 'light' && <Check className="size-3.5 text-primary ml-auto" />}
                </DropdownMenuItem>
                <DropdownMenuItem
                    onClick={() => setTheme('dark')}
                    className="flex items-center justify-between cursor-pointer rounded-lg px-2.5 py-1.5 text-xs font-medium"
                >
                    <div className="flex items-center gap-2">
                        <Moon className="size-4 text-sky-400" />
                        <span>Gelap</span>
                    </div>
                    {theme === 'dark' && <Check className="size-3.5 text-primary ml-auto" />}
                </DropdownMenuItem>
                <DropdownMenuItem
                    onClick={() => setTheme('system')}
                    className="flex items-center justify-between cursor-pointer rounded-lg px-2.5 py-1.5 text-xs font-medium"
                >
                    <div className="flex items-center gap-2">
                        <Monitor className="size-4 text-muted-foreground" />
                        <span>Sistem</span>
                    </div>
                    {theme === 'system' && <Check className="size-3.5 text-primary ml-auto" />}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default ThemeToggle;
