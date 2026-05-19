import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type { ColumnDef } from '@tanstack/react-table';
import { PaginatorLinks, PaginatorSummary } from '@/components/paginator-links';
import type { PaginatorMeta } from '@/components/paginator-links';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type { PaginatorMeta };

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
                    <PaginatorSummary meta={meta} />
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
