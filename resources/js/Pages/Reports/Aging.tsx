import { Head } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { DrawerLink } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';

interface Row { id: number; name: string; documents: number; total: number; current: number; d30: number; d60: number; d90: number; d90plus: number }
interface Props { kind: 'ar' | 'ap'; rows: Paginated<Row>; totals: Record<string, number>; filters: TableFilters; currency: string }

/** Receivables / payables ageing: what is open, by how many days past due. */
export default function Aging({ kind, rows, totals, filters, currency }: Props) {
    const ar = kind === 'ar';
    const money = (v: number) => formatMoney(v, currency);
    const cell = (v: number, danger = false) => <span className={`tabular ${v > 0 && danger ? 'text-danger fw-semibold' : ''}`}>{v > 0 ? money(v) : '—'}</span>;
    const columns: Column<Row>[] = [
        { key: 'name', label: ar ? 'Customer' : 'Supplier', render: (r) => <DrawerLink href={route(ar ? 'customers.show' : 'suppliers.show', r.id)} className="fw-semibold" title={r.name}>{r.name}</DrawerLink> },
        { key: 'current', label: 'Not due', align: 'end', render: (r) => cell(r.current) },
        { key: 'd30', label: '1–30', align: 'end', render: (r) => cell(r.d30) },
        { key: 'd60', label: '31–60', align: 'end', render: (r) => cell(r.d60, true) },
        { key: 'd90', label: '61–90', align: 'end', render: (r) => cell(r.d90, true) },
        { key: 'd90plus', label: '90+', align: 'end', render: (r) => cell(r.d90plus, true) },
        { key: 'total', label: 'Total open', align: 'end', render: (r) => <span className="tabular fw-bold">{money(r.total)}</span> },
        { key: 'statement', label: '', align: 'end', render: (r) => <a href={route(ar ? 'customers.statement' : 'suppliers.statement', r.id)} target="_blank" rel="noreferrer" className="btn btn-icon btn-soft" title="Statement" aria-label={`Statement of ${r.name}`}><i className="bi bi-file-earmark-text" /></a> },
    ];
    const t = (k: string) => totals[k] ?? 0;
    return (
        <>
            <Head title={ar ? 'Receivables ageing' : 'Payables ageing'} />
            <PageHeader icon="hourglass-split" title={ar ? 'Receivables ageing' : 'Payables ageing'} help={ar ? 'b2b-sales' : 'b2b-purchasing'}
                description={ar ? 'What customers owe you, by days past the due date.' : 'What you owe suppliers, by days past the due date.'} />
            <div className="row g-3 mb-3">
                <div className="col-6 col-xl-3"><KpiCard icon="cash-stack" label="Total open" value={money(t('total'))} accent="var(--chart-1)" /></div>
                <div className="col-6 col-xl-3"><KpiCard icon="check2-circle" label="Not due yet" value={money(t('current_amount'))} accent="var(--app-success)" /></div>
                <div className="col-6 col-xl-3"><KpiCard icon="alarm" label="1–60 days late" value={money(t('d30') + t('d60'))} accent="var(--app-warning)" /></div>
                <div className="col-6 col-xl-3"><KpiCard icon="exclamation-octagon" label="Over 60 days" value={money(t('d90') + t('d90plus'))} accent="var(--app-danger)" /></div>
            </div>
            <SectionCard icon="table" title={ar ? 'By customer' : 'By supplier'} flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters', 'totals']} searchPlaceholder="Search name…" empty={<EmptyState icon="emoji-smile" title="Nothing open" />} />
            </SectionCard>
        </>
    );
}
