import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

export function DataTableColumnHeader({ column, title, className, onSortChange }) {
    if (!column.getCanSort()) {
        return <div className={cn(className)}>{title}</div>;
    }

    const isSorted = column.getIsSorted();

    return (
        <div className={cn('flex items-center space-x-2', className)}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-3 h-8 data-[state=open]:bg-accent"
                    >
                        <span>{title}</span>
                        {isSorted === 'desc' ? (
                            <ArrowDown className="ml-2 size-4" />
                        ) : isSorted === 'asc' ? (
                            <ArrowUp className="ml-2 size-4" />
                        ) : (
                            <ChevronsUpDown className="ml-2 size-4" />
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem
                        onClick={() => {
                            column.toggleSorting(false);
                            onSortChange?.(column.id, 'asc');
                        }}
                    >
                        <ArrowUp className="mr-2 size-3.5 text-muted-foreground/70" />
                        Urutkan A-Z / Terendah
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onClick={() => {
                            column.toggleSorting(true);
                            onSortChange?.(column.id, 'desc');
                        }}
                    >
                        <ArrowDown className="mr-2 size-3.5 text-muted-foreground/70" />
                        Urutkan Z-A / Tertinggi
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
