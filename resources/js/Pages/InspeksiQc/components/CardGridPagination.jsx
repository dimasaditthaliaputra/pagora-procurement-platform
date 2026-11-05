import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

function buildPageNumbers(currentPage, lastPage) {
    if (lastPage <= 5) {
        return Array.from({ length: lastPage }, (_, idx) => idx + 1);
    }

    const pages = [1];
    const start = Math.max(2, currentPage - 1);
    const end = Math.min(lastPage - 1, currentPage + 1);

    if (start > 2) {
        pages.push('ellipsis-left');
    }

    for (let page = start; page <= end; page += 1) {
        pages.push(page);
    }

    if (end < lastPage - 1) {
        pages.push('ellipsis-right');
    }

    pages.push(lastPage);

    return pages;
}

export function CardGridPagination({
    meta,
    loading = false,
    onPageChange,
    onPerPageChange,
    perPageOptions = [10, 20, 30, 50],
}) {
    const currentPage = meta?.current_page ?? 1;
    const lastPage = Math.max(1, meta?.last_page ?? 1);
    const perPage = meta?.per_page ?? 10;
    const total = meta?.total ?? 0;
    const from = meta?.from ?? 0;
    const to = meta?.to ?? 0;

    const pageItems = lastPage > 1 ? buildPageNumbers(currentPage, lastPage) : [];

    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-border/60 px-2 py-4">
            <div className="text-sm text-muted-foreground text-center sm:text-left">
                Menampilkan{' '}
                <span className="font-medium text-foreground tabular-nums">
                    {total > 0 ? from : 0}
                </span>{' '}
                sampai{' '}
                <span className="font-medium text-foreground tabular-nums">
                    {total > 0 ? to : 0}
                </span>{' '}
                dari{' '}
                <span className="font-medium text-foreground tabular-nums">
                    {total}
                </span>{' '}
                total data
            </div>

            <div className="flex flex-wrap items-center justify-center gap-4 sm:gap-6 lg:gap-8">
                <div className="flex items-center gap-2">
                    <p className="text-sm font-medium text-muted-foreground whitespace-nowrap">
                        Baris per halaman
                    </p>
                    <Select
                        value={`${perPage}`}
                        disabled={loading}
                        onValueChange={(val) => onPerPageChange?.(Number(val))}
                    >
                        <SelectTrigger size="sm" className="w-[74px] bg-background">
                            <SelectValue placeholder={`${perPage}`} />
                        </SelectTrigger>
                        <SelectContent side="top" align="end">
                            {perPageOptions.map((size) => (
                                <SelectItem key={size} value={`${size}`}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center justify-center text-sm font-medium whitespace-nowrap px-1 text-muted-foreground">
                    Halaman{' '}
                    <span className="font-semibold text-foreground mx-1 tabular-nums">
                        {currentPage}
                    </span>{' '}
                    dari{' '}
                    <span className="font-semibold text-foreground ml-1 tabular-nums">
                        {lastPage}
                    </span>
                </div>

                <div className="flex items-center gap-1.5">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        className="hidden size-8 rounded-lg lg:inline-flex cursor-pointer"
                        onClick={() => onPageChange?.(1)}
                        disabled={loading || currentPage <= 1}
                    >
                        <span className="sr-only">Halaman pertama</span>
                        <ChevronsLeft className="size-4" />
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        className="size-8 rounded-lg cursor-pointer"
                        onClick={() => onPageChange?.(currentPage - 1)}
                        disabled={loading || currentPage <= 1}
                    >
                        <span className="sr-only">Halaman sebelumnya</span>
                        <ChevronLeft className="size-4" />
                    </Button>

                    {pageItems.map((item) =>
                        typeof item === 'string' ? (
                            <span
                                key={item}
                                className="px-1 text-sm text-muted-foreground select-none"
                            >
                                …
                            </span>
                        ) : (
                            <Button
                                key={item}
                                type="button"
                                size="icon-sm"
                                variant={item === currentPage ? 'default' : 'outline'}
                                className="size-8 rounded-lg text-xs font-semibold tabular-nums cursor-pointer"
                                onClick={() => {
                                    if (item !== currentPage) {
                                        onPageChange?.(item);
                                    }
                                }}
                                disabled={loading}
                                aria-current={item === currentPage ? 'page' : undefined}
                            >
                                {item}
                            </Button>
                        )
                    )}

                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        className="size-8 rounded-lg cursor-pointer"
                        onClick={() => onPageChange?.(currentPage + 1)}
                        disabled={loading || currentPage >= lastPage}
                    >
                        <span className="sr-only">Halaman selanjutnya</span>
                        <ChevronRight className="size-4" />
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        size="icon-sm"
                        className="hidden size-8 rounded-lg lg:inline-flex cursor-pointer"
                        onClick={() => onPageChange?.(lastPage)}
                        disabled={loading || currentPage >= lastPage}
                    >
                        <span className="sr-only">Halaman terakhir</span>
                        <ChevronsRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default CardGridPagination;
