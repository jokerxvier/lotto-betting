import { Link } from '@inertiajs/react';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/**
 * Shape of Laravel's LengthAwarePaginator JSON: only the bits the data
 * table needs to render server-driven pagination links + summary.
 */
export type PaginatorMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

type Props<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    meta?: PaginatorMeta;
    /**
     * When true, navigating pagination preserves Inertia state on the
     * non-paginated props (e.g. the search input value).
     */
    preserveStateOnPaginate?: boolean;
    /**
     * Extra Inertia `only:` keys to refresh when paginating — pass the
     * paginated prop's key (e.g. `users`) so other props stay cached.
     */
    onlyOnPaginate?: string[];
    emptyMessage?: string;
};

export function DataTable<TData>({
    columns,
    data,
    meta,
    preserveStateOnPaginate = true,
    onlyOnPaginate,
    emptyMessage = 'No results.',
}: Props<TData>) {
    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <div className="space-y-3">
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  header.column.columnDef
                                                      .header,
                                                  header.getContext(),
                                              )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center text-sm text-muted-foreground"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        ) : (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {meta && meta.last_page > 1 && (
                <div className="flex flex-col items-center justify-between gap-2 sm:flex-row">
                    <p className="text-xs text-muted-foreground">
                        Showing {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
                    </p>
                    <PaginatorLinks
                        meta={meta}
                        preserveState={preserveStateOnPaginate}
                        only={onlyOnPaginate}
                    />
                </div>
            )}
        </div>
    );
}

function PaginatorLinks({
    meta,
    preserveState,
    only,
}: {
    meta: PaginatorMeta;
    preserveState: boolean;
    only?: string[];
}) {
    const inertiaProps = {
        preserveState,
        preserveScroll: true,
        ...(only && only.length > 0 ? { only } : {}),
    };

    const head = meta.links[0];
    const tail = meta.links[meta.links.length - 1];
    const numbered = meta.links.slice(1, -1);

    return (
        <Pagination className="mx-0 w-auto justify-end">
            <PaginationContent>
                {head && (
                    <PaginationItem>
                        {head.url ? (
                            <Link href={head.url} {...inertiaProps}>
                                <PaginationPrevious />
                            </Link>
                        ) : (
                            <PaginationPrevious
                                aria-disabled
                                className="pointer-events-none opacity-50"
                            />
                        )}
                    </PaginationItem>
                )}
                {numbered.map((link, idx) =>
                    link.label === '...' ? (
                        <PaginationItem key={`ellipsis-${idx}`}>
                            <PaginationEllipsis />
                        </PaginationItem>
                    ) : (
                        <PaginationItem key={link.label + idx}>
                            {link.url ? (
                                <Link href={link.url} {...inertiaProps}>
                                    <PaginationLink isActive={link.active}>
                                        {link.label}
                                    </PaginationLink>
                                </Link>
                            ) : (
                                <PaginationLink
                                    aria-disabled
                                    className="pointer-events-none opacity-50"
                                >
                                    {link.label}
                                </PaginationLink>
                            )}
                        </PaginationItem>
                    ),
                )}
                {tail && (
                    <PaginationItem>
                        {tail.url ? (
                            <Link href={tail.url} {...inertiaProps}>
                                <PaginationNext />
                            </Link>
                        ) : (
                            <PaginationNext
                                aria-disabled
                                className="pointer-events-none opacity-50"
                            />
                        )}
                    </PaginationItem>
                )}
            </PaginationContent>
        </Pagination>
    );
}
