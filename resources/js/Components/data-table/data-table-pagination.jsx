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

export function DataTablePagination({
    meta,
    onPageChange,
    onPerPageChange,
}) {
    const currentPage = meta?.current_page ?? 1;
    const lastPage = meta?.last_page ?? 1;
    const perPage = meta?.per_page ?? 10;
    const total = meta?.total ?? 0;
    const from = meta?.from ?? 0;
    const to = meta?.to ?? 0;

    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 px-2 py-4">
            <div className="text-sm text-muted-foreground">
                Menampilkan <span className="font-medium text-foreground">{total > 0 ? from : 0}</span> sampai{' '}
                <span className="font-medium text-foreground">{total > 0 ? to : 0}</span> dari{' '}
                <span className="font-medium text-foreground">{total}</span> total data
            </div>

            <div className="flex flex-wrap items-center justify-center gap-4 sm:gap-6 lg:gap-8">
                <div className="flex items-center gap-2">
                    <p className="text-sm font-medium text-muted-foreground whitespace-nowrap">Baris per halaman</p>
                    <Select
                        value={`${perPage}`}
                        onValueChange={(val) => onPerPageChange?.(Number(val))}
                    >
                        <SelectTrigger className="h-8 w-[72px]">
                            <SelectValue placeholder={`${perPage}`} />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {[10, 20, 30, 50, 100].map((pageSize) => (
                                <SelectItem key={pageSize} value={`${pageSize}`}>
                                    {pageSize}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center justify-center text-sm font-medium whitespace-nowrap px-2 text-muted-foreground">
                    Halaman <span className="font-semibold text-foreground mx-1">{currentPage}</span> dari{' '}
                    <span className="font-semibold text-foreground ml-1">{lastPage}</span>
                </div>

                <div className="flex items-center gap-1.5">
                    <Button
                        variant="outline"
                        className="hidden size-8 p-0 lg:flex"
                        onClick={() => onPageChange?.(1)}
                        disabled={currentPage <= 1}
                    >
                        <span className="sr-only">Halaman pertama</span>
                        <ChevronsLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() => onPageChange?.(currentPage - 1)}
                        disabled={currentPage <= 1}
                    >
                        <span className="sr-only">Halaman sebelumnya</span>
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        className="size-8 p-0"
                        onClick={() => onPageChange?.(currentPage + 1)}
                        disabled={currentPage >= lastPage}
                    >
                        <span className="sr-only">Halaman selanjutnya</span>
                        <ChevronRight className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        className="hidden size-8 p-0 lg:flex"
                        onClick={() => onPageChange?.(lastPage)}
                        disabled={currentPage >= lastPage}
                    >
                        <span className="sr-only">Halaman terakhir</span>
                        <ChevronsRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
