import { router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import type { Paginated, TableFilters } from '@/types';
import EmptyState from './EmptyState';
import SkeletonRows from './Skeleton';

export interface Column<T> {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'start' | 'end' | 'center';
    className?: string;
    render?: (row: T) => ReactNode;
}

interface Props<T> {
    /** Laravel paginator for the current page (rows always come from the server). */
    page: Paginated<T>;
    columns: Column<T>[];
    filters: TableFilters;
    /** Inertia props to refresh on search/sort/page (partial reload). */
    only: string[];
    rowKey?: (row: T) => string | number;
    searchPlaceholder?: string;
    toolbar?: ReactNode;
    empty?: ReactNode;
    perPageOptions?: number[];
}

/**
 * Server-side table: searching, sorting, page size and paging all go to the server.
 * Global rule: never load a whole table into the browser.
 */
export default function DataTable<T extends { id?: number | string }>({
    page,
    columns,
    filters,
    only,
    rowKey = (r) => r.id as number,
    searchPlaceholder = 'Search…',
    toolbar,
    empty,
    perPageOptions = [10, 20, 50],
}: Props<T>) {
    const [q, setQ] = useState(filters.q ?? '');
    const [loading, setLoading] = useState(false);
    const first = useRef(true);
    const qRef = useRef(q);
    const debounce = useRef<number | undefined>(undefined);
    qRef.current = q;

    const visit = (params: Partial<TableFilters> & { page?: number }) => {
        // Always send what is in the search box now (a sort/page click must not use a stale search).
        window.clearTimeout(debounce.current);
        const query = { ...filters, q: qRef.current, ...params };
        Object.keys(query).forEach((k) => {
            const v = query[k as keyof typeof query];
            if (v === '' || v === undefined || v === null) delete query[k as keyof typeof query];
        });
        router.get(window.location.pathname, query, {
            only,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    };

    // Debounced server search.
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        debounce.current = window.setTimeout(() => visit({ q, page: 1 }), 350);
        return () => window.clearTimeout(debounce.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const sortBy = (key: string) => {
        const dir = filters.sort === key && filters.dir === 'asc' ? 'desc' : 'asc';
        visit({ sort: key, dir, page: 1 });
    };

    const sortIcon = (key: string) => {
        if (filters.sort !== key) return 'bi-arrow-down-up opacity-50';
        return filters.dir === 'desc' ? 'bi-sort-down' : 'bi-sort-up';
    };

    return (
        <div>
            <div className="dt-toolbar">
                <div className="search">
                    <i className="bi bi-search" />
                    <input type="search" className="form-control" placeholder={searchPlaceholder} value={q} onChange={(e) => setQ(e.target.value)} aria-label="Search" />
                </div>
                {loading && <span className="spinner-border spinner-border-sm text-secondary" role="status" aria-label="Loading" />}
                <div className="ms-auto d-flex gap-2 align-items-center">{toolbar}</div>
            </div>

            <div className={`table-responsive ${loading ? 'dt-loading' : ''}`}>
                <table className="table dt-table">
                    <thead>
                        <tr>
                            {columns.map((c) => (
                                <th
                                    key={c.key}
                                    className={`${c.sortable ? 'sortable' : ''} text-${c.align ?? 'start'}`}
                                    onClick={c.sortable ? () => sortBy(c.key) : undefined}
                                    aria-sort={filters.sort === c.key ? (filters.dir === 'desc' ? 'descending' : 'ascending') : undefined}
                                    scope="col"
                                >
                                    {c.label}
                                    {c.sortable && <i className={`bi ${sortIcon(c.key)} ms-1`} />}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {loading && page.data.length === 0 ? (
                            <SkeletonRows cols={columns.length} />
                        ) : page.data.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length}>{empty ?? <EmptyState title={filters.q ? 'No matches' : 'Nothing here yet'} hint={filters.q ? `Nothing matches “${filters.q}”.` : undefined} />}</td>
                            </tr>
                        ) : (
                            page.data.map((row) => (
                                <tr key={rowKey(row)}>
                                    {columns.map((c) => (
                                        <td key={c.key} className={`text-${c.align ?? 'start'} ${c.className ?? ''}`}>
                                            {c.render ? c.render(row) : String((row as Record<string, unknown>)[c.key] ?? '')}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <div className="dt-footer">
                <span className="tabular">
                    {page.total > 0 ? `Showing ${page.from}–${page.to} of ${page.total}` : 'No records'}
                </span>
                <div className="d-flex align-items-center gap-2">
                    <label className="d-flex align-items-center gap-2 mb-0">
                        <span>Per page</span>
                        <select className="form-select form-select-sm" style={{ width: 80 }} value={page.per_page} onChange={(e) => visit({ per_page: Number(e.target.value), page: 1 })}>
                            {perPageOptions.map((n) => (
                                <option key={n} value={n}>
                                    {n}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Pager current={page.current_page} last={page.last_page} onPage={(p) => visit({ page: p })} />
                </div>
            </div>
        </div>
    );
}

function Pager({ current, last, onPage }: { current: number; last: number; onPage: (p: number) => void }) {
    if (last <= 1) return null;

    const pages: (number | '…')[] = [];
    for (let p = 1; p <= last; p++) {
        if (p === 1 || p === last || Math.abs(p - current) <= 1) pages.push(p);
        else if (pages[pages.length - 1] !== '…') pages.push('…');
    }

    return (
        <nav aria-label="Pagination">
            <ul className="pagination pagination-sm">
                <li className={`page-item ${current === 1 ? 'disabled' : ''}`}>
                    <button className="page-link" onClick={() => onPage(current - 1)} aria-label="Previous">
                        <i className="bi bi-chevron-left" />
                    </button>
                </li>
                {pages.map((p, i) =>
                    p === '…' ? (
                        <li key={`gap-${i}`} className="page-item disabled">
                            <span className="page-link">…</span>
                        </li>
                    ) : (
                        <li key={p} className={`page-item ${p === current ? 'active' : ''}`}>
                            <button className="page-link" onClick={() => onPage(p)} aria-current={p === current ? 'page' : undefined}>
                                {p}
                            </button>
                        </li>
                    ),
                )}
                <li className={`page-item ${current === last ? 'disabled' : ''}`}>
                    <button className="page-link" onClick={() => onPage(current + 1)} aria-label="Next">
                        <i className="bi bi-chevron-right" />
                    </button>
                </li>
            </ul>
        </nav>
    );
}
