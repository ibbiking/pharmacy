import { Head, Link } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';

interface Row { id: number; name: string; stock: number; broken: boolean }

export default function StockGaps({ rows, filters, total }: { rows: Paginated<Row>; filters: TableFilters; total: number }) {
    const columns: Column<Row>[] = [
        { key: 'name', label: 'Product', render: (r) => <Link href={route('products.show', r.id)} className="fw-semibold text-reset">{r.name}</Link> },
        { key: 'why', label: 'Why', render: (r) => <span className="text-muted small">{r.broken ? 'Its alert unit is no longer in its packaging' : 'No alert level set'}</span> },
        { key: 'actions', label: '', align: 'end', render: (r) => <Link href={route('products.sale-price-preferences', r.id)} className="btn btn-sm btn-light"><i className="bi bi-bell me-1" />Set alert</Link> },
    ];

    return (
        <>
            <Head title="Stock rules gaps" />
            <PageHeader icon="bell-slash" title="Stock rules gaps" description={`${total} product(s) will never show up in Stock alerts because they have no usable alert level.`} />
            <SectionCard icon="table" title="Products without an alert" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search products…"
                    empty={filters.q ? undefined : <EmptyState icon="check2-circle" title="Every product has an alert level" />} />
            </SectionCard>
        </>
    );
}
