import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';

interface Row { id: number; code: string; name: string; symbol: string; exchange_rate: number; global: boolean; current: boolean; usage: number; can_manage: boolean }

interface Props {
    currencies: Paginated<Row>;
    filters: TableFilters & { scope: '' | 'global' | 'own' };
    stats: { global: number; own: number; current: { currency_code: string; name: string; symbol: string } | null };
    adding_global: boolean;
    open: 'create' | number | null;
}

export default function Currencies({ currencies, filters, stats, adding_global, open }: Props) {
    const [editing, setEditing] = useState<Row | 'new' | null>(null);
    const [archiving, setArchiving] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (open === 'create') setEditing('new');
        else if (typeof open === 'number') setEditing(currencies.data.find((c) => c.id === open) ?? null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const filter = (scope: Props['filters']['scope']) =>
        router.get(route('currencies.index'), { ...filters, scope, page: 1 }, { only: ['currencies', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const archive = () => {
        if (!archiving) return;
        router.delete(route('currencies.destroy', archiving.id), { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => { setBusy(false); setArchiving(null); } });
    };

    const columns: Column<Row>[] = [
        {
            key: 'currency_code', label: 'Code', sortable: true,
            render: (c) => (
                <span className="d-flex align-items-center gap-2 fw-semibold">
                    <span className="avatar-tile" style={{ ['--accent' as string]: c.global ? 'var(--chart-1)' : 'var(--chart-4)' }}>{c.symbol.slice(0, 3)}</span>
                    {c.code}
                    {c.current && <span className="pill" style={{ ['--accent' as string]: 'var(--app-success)' }}>your currency</span>}
                </span>
            ),
        },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'symbol', label: 'Symbol', sortable: true, className: 'tabular' },
        { key: 'scope', label: 'List', render: (c) => (c.global ? <span className="text-muted">Global</span> : <span>Your business</span>) },
        {
            key: 'actions', label: '', align: 'end',
            render: (c) => c.can_manage ? (
                <div className="d-inline-flex gap-1">
                    <button type="button" className="btn btn-icon btn-soft" onClick={() => setEditing(c)} title={`Edit ${c.code}`} aria-label={`Edit ${c.code}`}><i className="bi bi-pencil" /></button>
                    <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setArchiving(c)} title={`Archive ${c.code}`} aria-label={`Archive ${c.code}`} disabled={c.usage > 0}><i className="bi bi-archive" /></button>
                </div>
            ) : null,
        },
    ];

    return (
        <>
            <Head title="Currencies" />
            <PageHeader
                icon="currency-exchange"
                title="Currencies"
                description="The global list plus any currency your business adds. Choose your business currency in Business settings."
                actions={
                    <button type="button" className="btn btn-primary" onClick={() => setEditing('new')}>
                        <i className="bi bi-plus-lg me-1" />{adding_global ? 'Add global currency' : 'Add currency'}
                    </button>
                }
            />
            <StatStrip
                stats={[
                    { key: 'all', icon: 'collection', label: 'All', value: stats.global + stats.own, accent: 'var(--chart-1)', onClick: () => filter(''), selected: !filters.scope },
                    { key: 'global', icon: 'globe2', label: 'Global', value: stats.global, accent: 'var(--chart-2)', onClick: () => filter('global'), selected: filters.scope === 'global' },
                    { key: 'own', icon: 'shop', label: 'Added by you', value: stats.own, accent: 'var(--chart-4)', onClick: () => filter('own'), selected: filters.scope === 'own' },
                    { key: 'current', icon: 'check2-circle', label: 'Your currency', value: stats.current ? `${stats.current.currency_code} (${stats.current.symbol})` : 'Not set', accent: stats.current ? 'var(--app-success)' : 'var(--app-warning)' },
                ]}
            />
            <SectionCard icon="table" title="All currencies" subtitle="Search by code or name" flush>
                <DataTable page={currencies} columns={columns} filters={filters} only={['currencies', 'filters']} searchPlaceholder="Search code or name…" />
            </SectionCard>

            <CurrencyModal currency={editing} global={adding_global} onClose={() => setEditing(null)} />
            <ConfirmDialog
                open={archiving !== null}
                title="Archive currency?"
                message={archiving ? `“${archiving.code} — ${archiving.name}” will no longer be offered.` : ''}
                confirmLabel="Archive"
                danger
                busy={busy}
                onConfirm={archive}
                onClose={() => setArchiving(null)}
            />
        </>
    );
}

function CurrencyModal({ currency, global, onClose }: { currency: Row | 'new' | null; global: boolean; onClose: () => void }) {
    const isNew = currency === 'new';
    const form = useForm({ currency_code: '', name: '', symbol: '', exchange_rate: '' as string | number });

    useEffect(() => {
        form.clearErrors();
        if (currency === 'new') form.setData({ currency_code: '', name: '', symbol: '', exchange_rate: '' });
        else if (currency) form.setData({ currency_code: currency.code, name: currency.name, symbol: currency.symbol, exchange_rate: currency.exchange_rate });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currency]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };
        if (isNew) form.post(route('currencies.store'), options);
        else if (currency) form.put(route('currencies.update', currency.id), options);
    };
    const field = (key: 'currency_code' | 'name' | 'symbol', label: string, placeholder: string, max: number) => (
        <div className="mb-3">
            <label className="form-label fw-semibold" htmlFor={`cur-${key}`}>{label}</label>
            <input id={`cur-${key}`} className={`form-control ${form.errors[key] ? 'is-invalid' : ''}`} value={form.data[key]} maxLength={max} placeholder={placeholder}
                onChange={(e) => form.setData(key, key === 'currency_code' ? e.target.value.toUpperCase() : e.target.value)} />
            {form.errors[key] && <div className="invalid-feedback">{form.errors[key]}</div>}
        </div>
    );

    return (
        <Modal
            open={currency !== null}
            title={isNew ? (global ? 'Add global currency' : 'Add currency') : 'Edit currency'}
            icon="currency-exchange"
            onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-light" onClick={onClose}>Cancel</button>
                    <button type="submit" form="currency-form" className="btn btn-primary" disabled={form.processing}>
                        {form.processing && <span className="spinner-border spinner-border-sm me-2" />}
                        {isNew ? 'Add currency' : 'Save changes'}
                    </button>
                </>
            }
        >
            <form id="currency-form" onSubmit={submit} noValidate>
                <div className="row g-2">
                    <div className="col-5">{field('currency_code', 'Code', 'e.g. AED', 10)}</div>
                    <div className="col-7">{field('symbol', 'Symbol', 'e.g. د.إ', 20)}</div>
                </div>
                {field('name', 'Name', 'e.g. UAE Dirham', 100)}
                <div>
                    <label className="form-label fw-semibold" htmlFor="cur-rate">Exchange rate <small className="text-muted fw-normal">optional, for your reference</small></label>
                    <input id="cur-rate" type="number" step="any" min={0} className={`form-control ${form.errors.exchange_rate ? 'is-invalid' : ''}`} value={form.data.exchange_rate} placeholder="1"
                        onChange={(e) => form.setData('exchange_rate', e.target.value)} />
                    {form.errors.exchange_rate && <div className="invalid-feedback">{form.errors.exchange_rate}</div>}
                </div>
                {!global && isNew && <p className="small text-muted mt-3 mb-0">Only your business sees the currencies it adds.</p>}
            </form>
        </Modal>
    );
}
