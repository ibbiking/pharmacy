import { Deferred, Head, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import EmptyState from '@/Components/EmptyState';
import { DrawerLink } from '@/Components/Drawer';
import { formatMoney, formatNumber } from '@/lib/format';

interface Row { id: string; product_id: number; name: string; warehouse: string; qty: number; expired_qty: number; value: number; batches: number; unit: string | null }
interface Props {
    rows: Paginated<Row>;
    filters: TableFilters & { warehouse_id?: number | null };
    warehouse: Option | null;
    kpis?: { value: number; expired_value: number; items: number; warehouses: number };
    currency: string;
}

/** Stock by warehouse and its value at cost (Inventory Pro). */
export default function Valuation({ rows, filters, warehouse, kpis, currency }: Props) {
    const money = (v: number) => formatMoney(v, currency);
    const columns: Column<Row>[] = [
        { key: 'name', label: 'Item', sortable: true, render: (r) => <DrawerLink href={route('products.show', r.product_id)} className="fw-semibold text-reset" aria-label={r.name}>{r.name}</DrawerLink> },
        { key: 'warehouse', label: 'Warehouse', render: (r) => <span className="text-muted">{r.warehouse}</span> },
        { key: 'qty', label: 'In stock', sortable: true, align: 'end', render: (r) => <span className="tabular">{formatNumber(r.qty, 0)} {r.unit}{r.expired_qty > 0 && <small className="d-block text-danger">{formatNumber(r.expired_qty, 0)} expired</small>}</span> },
        { key: 'batches', label: 'Batches', align: 'end', render: (r) => <span className="tabular">{r.batches}</span> },
        { key: 'value', label: 'Value (cost)', sortable: true, align: 'end', render: (r) => <span className="tabular fw-semibold">{money(r.value)}</span> },
    ];
    return (
        <>
            <Head title="Stock valuation" />
            <PageHeader icon="clipboard-data" title="Stock by warehouse" help="inventory-pro" description="What you hold in each warehouse and what it cost (each batch at its own paid cost)." />
            <Deferred data="kpis" fallback={<div className="kpi-grid mb-3"><div className="skeleton" style={{ height: 88 }} /></div>}>
                <div className="row g-3 mb-3">
                    <div className="col-sm-6 col-xl-3"><KpiCard icon="cash-stack" label="Stock value" value={money(kpis?.value ?? 0)} accent="var(--chart-1)" /></div>
                    <div className="col-sm-6 col-xl-3"><KpiCard icon="calendar-x" label="Expired (at cost)" value={money(kpis?.expired_value ?? 0)} accent="var(--app-danger)" /></div>
                    <div className="col-sm-6 col-xl-3"><KpiCard icon="box-seam" label="Items in stock" value={kpis?.items ?? 0} accent="var(--chart-3)" /></div>
                    <div className="col-sm-6 col-xl-3"><KpiCard icon="buildings" label="Warehouses" value={kpis?.warehouses ?? 0} accent="var(--chart-6)" /></div>
                </div>
            </Deferred>
            <SectionCard icon="table" title="Stock" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters', 'warehouse', 'kpis']} searchPlaceholder="Search items…"
                    toolbar={<div style={{ minWidth: 220 }}><AsyncSelect entity="warehouses" value={warehouse} placeholder="Every warehouse"
                        onChange={(v) => router.get(route('inventory.valuation'), { ...filters, warehouse_id: v?.value ?? null, page: 1 }, { preserveState: true, replace: true })} /></div>}
                    empty={<EmptyState icon="box-seam" title="No stock here" />} />
            </SectionCard>
        </>
    );
}
