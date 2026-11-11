import { Head, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import { DrawerLink } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';

interface Row { id: number; number: string; party: string | null; date: string; amount: number; unapplied: number; method: string; reference: string | null; documents: number }
interface Props { direction: 'in' | 'out'; rows: Paginated<Row>; filters: TableFilters & { status?: string | null }; stats: Record<string, number>; canCreate: boolean; currency: string }

export default function PaymentsIndex({ direction, rows, filters, stats, canCreate, currency }: Props) {
    const inbound = direction === 'in';
    const side = inbound ? 'selling' : 'buying';
    const title = inbound ? 'Payments received' : 'Bill payments';
    const money = (v: number) => formatMoney(v, currency);
    const filter = (status: string | null) => router.get(route(`${side}.payments.index`), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, replace: true });
    const columns: Column<Row>[] = [
        { key: 'number', label: 'Number', render: (r) => <DrawerLink href={route(`${side}.payments.show`, r.id)} className="fw-semibold" aria-label={`Payment ${r.number}`}>{r.number}</DrawerLink> },
        { key: 'party', label: inbound ? 'Customer' : 'Supplier', render: (r) => <span className="fw-semibold">{r.party}</span> },
        { key: 'date', label: 'Date', render: (r) => <span className="text-muted text-nowrap">{r.date}</span> },
        { key: 'method', label: 'Method', render: (r) => <span>{r.method}{r.reference && <small className="d-block text-muted">{r.reference}</small>}</span> },
        { key: 'amount', label: 'Amount', align: 'end', render: (r) => <span className="tabular fw-semibold">{money(r.amount)}</span> },
        { key: 'unapplied', label: 'Not applied', align: 'end', render: (r) => <span className="tabular">{r.unapplied > 0 ? money(r.unapplied) : '—'}</span> },
    ];
    return (
        <>
            <Head title={title} />
            <PageHeader icon="cash-coin" title={title} help={inbound ? 'b2b-sales' : 'b2b-purchasing'}
                description={inbound ? 'Money customers paid you, and the invoices it settled. Anything not applied stays as their credit.' : 'Money you paid suppliers, and the bills it settled.'}
                actions={canCreate && <DrawerLink href={route(`${side}.payments.create`)} className="btn btn-primary" title={inbound ? 'Record payment' : 'Pay bills'}><i className="bi bi-plus-lg me-1" />{inbound ? 'Record payment' : 'Pay bills'}</DrawerLink>} />
            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: 'All', value: stats.all ?? 0, accent: 'var(--chart-1)', onClick: () => filter(null), selected: !filters.status },
                { key: 'month', icon: 'calendar3', label: `This month · ${money(stats.month_amount ?? 0)}`, value: stats.month ?? 0, accent: 'var(--app-success)', onClick: () => filter('month'), selected: filters.status === 'month' },
                { key: 'unapplied', icon: 'piggy-bank', label: `Not applied · ${money(stats.unapplied_amount ?? 0)}`, value: stats.unapplied ?? 0, accent: 'var(--app-warning)', onClick: () => filter('unapplied'), selected: filters.status === 'unapplied' },
            ]} />
            <SectionCard icon="table" title={title} flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder="Search number, reference or name…"
                    empty={<EmptyState icon="cash-coin" title="No payments yet" />} />
            </SectionCard>
        </>
    );
}
