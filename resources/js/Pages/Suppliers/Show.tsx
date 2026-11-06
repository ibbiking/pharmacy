import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import Chart from '@/Components/Chart';
import ConfirmDialog from '@/Components/ConfirmDialog';
import SupplierFormModal from '@/Components/Suppliers/SupplierFormModal';
import { formatMoney, formatNumber, initials } from '@/lib/format';
import type { SupplierRow } from './Index';

interface PurchaseRow { id: number; product: string; unit: string | null; quantity: number; unit_cost: number; total: number; batch: string | null; invoice: string | null; expiry: string | null; expired: boolean; date: string | null }
interface Props {
    supplier: SupplierRow & { products_count: number; since: string | null };
    purchases: Paginated<PurchaseRow>;
    filters: TableFilters;
    months: { label: string; total: number }[];
    currency: string;
    canManage: boolean;
    canSeePurchases: boolean;
}

export default function SupplierShow({ supplier, purchases, filters, months, currency, canManage, canSeePurchases }: Props) {
    const [editing, setEditing] = useState(false);
    const [archiving, setArchiving] = useState(false);

    const chart = useMemo(() => ({
        grid: { left: 8, right: 12, top: 16, bottom: 8, containLabel: true },
        tooltip: { trigger: 'axis' as const, valueFormatter: (v: unknown) => formatMoney(Number(v), currency) },
        xAxis: { type: 'category' as const, data: months.map((m) => m.label) },
        yAxis: { type: 'value' as const },
        series: [{ type: 'bar' as const, data: months.map((m) => m.total), barMaxWidth: 28, itemStyle: { borderRadius: [6, 6, 0, 0] } }],
    }), [months, currency]);
    const hasHistory = months.some((m) => m.total > 0);

    const columns: Column<PurchaseRow>[] = [
        { key: 'date', label: 'Date', render: (r) => <span className="text-muted">{r.date}</span> },
        { key: 'product', label: 'Item', render: (r) => <div><span className="fw-semibold">{r.product}</span>{r.invoice && <div className="small text-muted">Bill {r.invoice}</div>}</div> },
        { key: 'quantity', label: 'Qty', align: 'end', className: 'tabular', render: (r) => <>{formatNumber(r.quantity, 0)} <small className="text-muted">{r.unit}</small></> },
        { key: 'unit_cost', label: 'Unit cost', align: 'end', className: 'tabular', render: (r) => formatMoney(r.unit_cost, currency) },
        { key: 'total', label: 'Total', align: 'end', className: 'tabular fw-semibold', render: (r) => formatMoney(r.total, currency) },
        { key: 'batch', label: 'Batch / expiry', render: (r) => (
            <div className="small">{r.batch ?? '—'}{r.expiry && <span className="pill ms-2" style={{ ['--accent' as string]: r.expired ? 'var(--app-danger)' : 'var(--chart-8)' }}>{r.expired ? 'Expired ' : 'Exp '}{r.expiry}</span>}</div>
        ) },
        ...(canSeePurchases ? [{ key: 'open', label: '', align: 'end' as const, render: (r: PurchaseRow) => <Link href={route('purchases.show', r.id)} className="btn btn-icon btn-soft" aria-label={`Open purchase ${r.id}`}><i className="bi bi-box-arrow-up-right" /></Link> }] : []),
    ];

    return (
        <>
            <Head title={supplier.name} />
            <div className="mb-2"><Link href={route('suppliers.index')} className="small text-muted text-decoration-none"><i className="bi bi-arrow-left me-1" />All suppliers</Link></div>
            <PageHeader icon="truck" title={supplier.name} description={[supplier.company, supplier.since && `Supplier since ${supplier.since}`].filter(Boolean).join(' · ') || undefined}
                actions={canManage && (supplier.archived ? (
                    <button type="button" className="btn btn-primary" onClick={() => router.post(route('suppliers.restore', supplier.id))}><i className="bi bi-arrow-counterclockwise me-1" />Restore</button>
                ) : (
                    <>
                        <button type="button" className="btn btn-light" onClick={() => setArchiving(true)}><i className="bi bi-archive me-1" />Archive</button>
                        <button type="button" className="btn btn-primary" onClick={() => setEditing(true)}><i className="bi bi-pencil me-1" />Edit</button>
                    </>
                ))} />

            {supplier.archived && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-warning)' }}><i className="bi bi-archive" /><div><strong>Archived supplier</strong><p>Not offered on new purchases. History stays below.</p></div></div>
            )}

            <div className="row g-3 mb-3">
                <div className="col-sm-6 col-xl-3"><KpiCard icon="cash-stack" label="Total purchased" value={formatMoney(supplier.purchased_total, currency)} accent="var(--chart-1)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="receipt" label="Purchases" value={supplier.purchases} accent="var(--chart-2)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="capsule" label="Different items" value={supplier.products_count} accent="var(--chart-3)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="calendar-check" label="Last purchase" value={supplier.last_purchase ?? 'Never'} accent="var(--chart-4)" /></div>
            </div>

            <div className="row g-3">
                <div className="col-xl-4">
                    <SectionCard icon="person-vcard" title="Contact" accent="var(--chart-6)">
                        <div className="d-flex align-items-center gap-3 mb-3">
                            <span className="avatar-tile" style={{ width: 52, height: 52, fontSize: '1rem', ['--accent' as string]: `var(--chart-${(supplier.id % 8) + 1})` }}>{initials(supplier.name)}</span>
                            <div><strong>{supplier.name}</strong>{supplier.company && <div className="small text-muted">{supplier.company}</div>}</div>
                        </div>
                        <dl className="contact-list mb-0">
                            <Item icon="telephone" label="Phone">{supplier.phone ? <a href={`tel:${supplier.phone}`}>{supplier.phone}</a> : null}</Item>
                            <Item icon="envelope" label="Email">{supplier.email ? <a href={`mailto:${supplier.email}`} className="text-break">{supplier.email}</a> : null}</Item>
                            <Item icon="geo-alt" label="Address">{supplier.address}</Item>
                            <Item icon="box-seam" label="Supplies">{supplier.product}</Item>
                            <Item icon="sticky" label="Notes">{supplier.comment}</Item>
                        </dl>
                    </SectionCard>
                </div>
                <div className="col-xl-8">
                    <SectionCard icon="bar-chart-line" title="Purchases per month" subtitle="Last 12 months" accent="var(--chart-1)">
                        {hasHistory ? <Chart option={chart} height={240} ariaLabel={`Purchases from ${supplier.name} per month`} /> : <EmptyState icon="bar-chart" title="No purchases in the last 12 months" />}
                    </SectionCard>
                </div>
            </div>

            <div className="mt-3">
                <SectionCard icon="clock-history" title="Purchase history" subtitle="Newest first" flush>
                    <DataTable page={purchases} columns={columns} filters={filters} only={['purchases', 'filters']} searchPlaceholder="Search item, batch or bill no…"
                        empty={filters.q ? undefined : <EmptyState icon="box-seam" title="No purchases from this supplier yet" hint="Purchases you record with this supplier appear here." />} />
                </SectionCard>
            </div>

            <SupplierFormModal supplier={editing ? supplier : null} onClose={() => setEditing(false)} />
            <ConfirmDialog open={archiving} title="Archive supplier?" danger confirmLabel="Archive"
                message={`“${supplier.name}” won’t be offered on new purchases. Its ${supplier.purchases} past purchase(s) keep showing the name.`}
                onConfirm={() => router.delete(route('suppliers.destroy', supplier.id), { onFinish: () => setArchiving(false) })}
                onClose={() => setArchiving(false)} />
        </>
    );
}

function Item({ icon, label, children }: { icon: string; label: string; children: ReactNode }) {
    return (
        <div className="d-flex gap-3 py-2 border-bottom" style={{ borderColor: 'var(--app-border)' }}>
            <i className={`bi bi-${icon} text-muted`} />
            <div className="min-w-0"><dt className="small text-muted fw-normal">{label}</dt><dd className="mb-0">{children ?? <span className="text-muted">—</span>}</dd></div>
        </div>
    );
}
