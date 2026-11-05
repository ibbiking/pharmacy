import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import Chart from '@/Components/Chart';
import { formatNumber } from '@/lib/format';

interface TaxRow {
    id: number;
    name: string;
    rate: number;
    usage: number;
    created_at: string | null;
}

interface Props {
    taxes: Paginated<TaxRow>;
    filters: TableFilters & { usage?: '' | 'used' | 'unused' };
    stats: { count: number; average_rate: number; highest_rate: number; in_use: number };
    open: 'create' | number | null;
}

export default function TaxesIndex({ taxes, filters, stats, open }: Props) {
    const [editing, setEditing] = useState<TaxRow | 'new' | null>(null);
    const [archiving, setArchiving] = useState<TaxRow | null>(null);
    const [archivingBusy, setArchivingBusy] = useState(false);

    // Old links (/taxes/create, /taxes/{id}/edit) open the matching form.
    useEffect(() => {
        if (open === 'create') setEditing('new');
        else if (typeof open === 'number') setEditing(taxes.data.find((t) => t.id === open) ?? null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const columns: Column<TaxRow>[] = [
        {
            key: 'name',
            label: 'Tax',
            sortable: true,
            render: (t) => (
                <span className="d-flex align-items-center gap-2 fw-semibold">
                    <span className="pill" style={{ ['--accent' as string]: 'var(--chart-' + ((t.id % 8) + 1) + ')' }}>
                        <i className="bi bi-percent" />
                    </span>
                    {t.name}
                </span>
            ),
        },
        { key: 'rate', label: 'Rate', sortable: true, align: 'end', className: 'tabular fw-semibold', render: (t) => `${formatNumber(t.rate)}%` },
        {
            key: 'usage',
            label: 'Used on purchases',
            sortable: true,
            align: 'end',
            className: 'tabular',
            render: (t) => (t.usage > 0 ? <span className="pill" style={{ ['--accent' as string]: 'var(--app-success)' }}>{t.usage}</span> : <span className="text-muted">—</span>),
        },
        { key: 'created_at', label: 'Added', sortable: true, render: (t) => <span className="text-muted">{t.created_at}</span> },
        {
            key: 'actions',
            label: '',
            align: 'end',
            render: (t) => (
                <div className="d-inline-flex gap-1">
                    <button type="button" className="btn btn-icon btn-soft" onClick={() => setEditing(t)} title={`Edit ${t.name}`} aria-label={`Edit ${t.name}`}>
                        <i className="bi bi-pencil" />
                    </button>
                    <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setArchiving(t)} title={`Archive ${t.name}`} aria-label={`Archive ${t.name}`}>
                        <i className="bi bi-archive" />
                    </button>
                </div>
            ),
        },
    ];

    const chartOption = useMemo(
        () => ({
            grid: { left: 8, right: 16, top: 16, bottom: 8, containLabel: true },
            tooltip: { trigger: 'axis' as const, valueFormatter: (v: unknown) => `${v}%` },
            xAxis: { type: 'value' as const, axisLabel: { formatter: '{value}%' } },
            yAxis: { type: 'category' as const, data: taxes.data.map((t) => t.name), inverse: true },
            series: [{ type: 'bar' as const, data: taxes.data.map((t) => t.rate), barMaxWidth: 18, itemStyle: { borderRadius: [0, 6, 6, 0] }, colorBy: 'data' as const }],
        }),
        [taxes.data],
    );

    const filterUsage = (usage: '' | 'used' | 'unused') =>
        router.get(route('taxes.index'), { ...filters, usage, page: 1 }, { only: ['taxes', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const archive = () => {
        if (!archiving) return;
        router.delete(route('taxes.destroy', archiving.id), {
            preserveScroll: true,
            onStart: () => setArchivingBusy(true),
            onFinish: () => {
                setArchivingBusy(false);
                setArchiving(null);
            },
        });
    };

    return (
        <>
            <Head title="Taxes" />
            <PageHeader
                icon="percent"
                title="Taxes"
                help="taxes"
                description="Named taxes you attach to purchases. A sale tax is added to the price at the POS or included in it, depending on your sale preferences."
                actions={
                    <button type="button" className="btn btn-primary" onClick={() => setEditing('new')}>
                        <i className="bi bi-plus-lg me-1" />
                        Add tax
                    </button>
                }
            />

            <StatStrip
                stats={[
                    { key: 'all', icon: 'collection', label: 'All taxes', value: stats.count, accent: 'var(--chart-1)', onClick: () => filterUsage(''), selected: !filters.usage },
                    { key: 'used', icon: 'check2-circle', label: 'In use', value: stats.in_use, accent: 'var(--chart-3)', onClick: () => filterUsage('used'), selected: filters.usage === 'used' },
                    { key: 'unused', icon: 'circle', label: 'Not used yet', value: stats.count - stats.in_use, accent: 'var(--chart-8)', onClick: () => filterUsage('unused'), selected: filters.usage === 'unused' },
                    { key: 'avg', icon: 'bar-chart', label: 'Average rate', value: `${formatNumber(stats.average_rate)}%`, accent: 'var(--chart-2)' },
                    { key: 'max', icon: 'graph-up-arrow', label: 'Highest rate', value: `${formatNumber(stats.highest_rate)}%`, accent: 'var(--chart-4)' },
                ]}
            />

            <div className="row g-4">
                <div className="col-xl-8">
                    <SectionCard icon="table" title="All taxes" subtitle="Search, sort and page through your taxes" flush>
                        <DataTable
                            page={taxes}
                            columns={columns}
                            filters={filters}
                            only={['taxes', 'filters']}
                            searchPlaceholder="Search taxes…"
                            empty={
                                filters.q ? undefined : (
                                    <EmptyState
                                        icon="percent"
                                        title="No taxes yet"
                                        hint="Add the taxes you pay or charge (e.g. GST 17%) so purchases can carry them."
                                        action={
                                            <button type="button" className="btn btn-primary btn-sm" onClick={() => setEditing('new')}>
                                                Add your first tax
                                            </button>
                                        }
                                    />
                                )
                            }
                        />
                    </SectionCard>
                </div>
                <div className="col-xl-4">
                    <SectionCard icon="bar-chart-line" title="Rates on this page" subtitle="Compare the taxes listed" accent="var(--chart-2)">
                        {taxes.data.length ? <Chart option={chartOption} height={Math.max(180, taxes.data.length * 34)} ariaLabel="Bar chart of tax rates" /> : <EmptyState icon="bar-chart" title="No data to chart" />}
                    </SectionCard>
                    <SectionCard icon="lightbulb" title="How taxes work" accent="var(--chart-4)">
                        <ul className="small mb-0 ps-3 text-muted">
                            <li className="mb-2">Pick taxes on a purchase under <strong>Purchase Tax</strong> (what you paid) and <strong>Sale Tax</strong> (what the customer pays).</li>
                            <li className="mb-2">With <em>Sale price including tax</em> on, the sale tax is already inside the price and is shown to owners only.</li>
                            <li>Archiving a tax keeps it on purchases that already use it; it just can't be picked for new ones.</li>
                        </ul>
                    </SectionCard>
                </div>
            </div>

            <TaxFormModal tax={editing} onClose={() => setEditing(null)} />
            <ConfirmDialog
                open={archiving !== null}
                title="Archive tax?"
                message={archiving ? `“${archiving.name}” will no longer be available for new purchases.${archiving.usage ? ` It stays on the ${archiving.usage} purchase line(s) that already use it.` : ''}` : ''}
                confirmLabel="Archive"
                danger
                busy={archivingBusy}
                onConfirm={archive}
                onClose={() => setArchiving(null)}
            />
        </>
    );
}

function TaxFormModal({ tax, onClose }: { tax: TaxRow | 'new' | null; onClose: () => void }) {
    const isNew = tax === 'new';
    const form = useForm({ name: '', rate: '' as string | number });

    useEffect(() => {
        form.clearErrors();
        if (tax === 'new') form.setData({ name: '', rate: '' });
        else if (tax) form.setData({ name: tax.name, rate: tax.rate });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tax]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };
        if (isNew) form.post(route('taxes.store'), options);
        else if (tax) form.put(route('taxes.update', tax.id), options);
    };

    return (
        <Modal
            open={tax !== null}
            title={isNew ? 'Add tax' : 'Edit tax'}
            icon="percent"
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-light" onClick={onClose}>
                        Cancel
                    </button>
                    <button type="submit" form="tax-form" className="btn btn-primary" disabled={form.processing}>
                        {form.processing && <span className="spinner-border spinner-border-sm me-2" />}
                        {isNew ? 'Add tax' : 'Save changes'}
                    </button>
                </>
            }
        >
            <form id="tax-form" onSubmit={submit} noValidate>
                <div className="mb-3">
                    <label className="form-label fw-semibold" htmlFor="tax-name">
                        Name
                    </label>
                    <input
                        id="tax-name"
                        className={`form-control ${form.errors.name ? 'is-invalid' : ''}`}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="e.g. GST"
                        maxLength={100}
                        autoFocus
                    />
                    {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                </div>
                <div>
                    <label className="form-label fw-semibold" htmlFor="tax-rate">
                        Rate
                    </label>
                    <div className="input-group has-validation">
                        <input
                            id="tax-rate"
                            type="number"
                            step="0.01"
                            min={0}
                            max={100}
                            className={`form-control ${form.errors.rate ? 'is-invalid' : ''}`}
                            value={form.data.rate}
                            onChange={(e) => form.setData('rate', e.target.value)}
                            placeholder="17"
                        />
                        <span className="input-group-text">%</span>
                        {form.errors.rate && <div className="invalid-feedback">{form.errors.rate}</div>}
                    </div>
                </div>
            </form>
        </Modal>
    );
}
