import { Link } from '@inertiajs/react';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';

/**
 * Shape of Laravel's LengthAwarePaginator JSON. Only the bits the
 * paginator UI needs to render server-driven pagination links + a
 * "showing m–n of total" summary.
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

type Props = {
    meta: PaginatorMeta;
    /**
     * Preserve Inertia state on the non-paginated props (e.g. search input value).
     */
    preserveState?: boolean;
    /**
     * Inertia `only:` keys — pass the paginated prop's key so other props stay cached.
     */
    only?: string[];
};

export function PaginatorLinks({ meta, preserveState = true, only }: Props) {
    if (meta.last_page <= 1) {
        return null;
    }

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

/**
 * One-line summary line — "Showing 1–25 of 137".
 */
export function PaginatorSummary({ meta }: { meta: PaginatorMeta }) {
    return (
        <p className="text-xs text-muted-foreground tabular-nums">
            Showing {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
        </p>
    );
}
