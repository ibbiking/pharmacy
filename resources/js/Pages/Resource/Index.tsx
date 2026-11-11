import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import { DrawerLink, SidePanel } from '@/Components/Drawer';
import { toast } from '@/Components/Toaster';
import { formatMoney, formatNumber } from '@/lib/format';

export interface Field {
    key: string; label: string; type: 'text' | 'email' | 'textarea' | 'number' | 'money' | 'date' | 'switch' | 'select' | 'lookup';
    required?: boolean; options?: { value: string | number; label: string }[]; entity?: string; col?: number; hint?: string; placeholder?: string;
}
interface ColumnDef { key: string; label: string; type?: 'text' | 'money' | 'number' | 'date' | 'pill' | 'title'; align?: 'start' | 'end'; sortable?: boolean }
interface Schema {
    title: string; singular: string; icon: string; accent: string; help: string | null; route: string; description: string | null;
    fields: Field[]; columns: ColumnDef[]; statuses: { key: string; label: string; icon: string; accent: string }[]; show: string | null; canManage: boolean; createLabel: string | null;
}
type Cell = string | number | boolean | null | { text: string; accent?: string; sub?: string | null };
interface Row { id: number; archived: boolean; cells: Record<string, Cell>; form: Record<string, unknown>; locked?: string | null }
interface Props {
    schema: Schema;
    rows: Paginated<Row>;
    filters: TableFilters & { status?: string | null };
    stats: Record<string, number>;
    open: 'create' | null;
    currency?: string;
    /** One setting shown above the list, e.g. "The POS sells from [warehouse]". */
    picker?: { label: string; hint: string; entity: string; field: string; route: string; placeholder: string; value: Option | null; canChange: boolean };
}

/** Generic list + side-panel form for simple records (App\Http\Controllers\Admin\Resource\ResourceController). */
export default function ResourceIndex({ schema, rows, filters, stats, open, currency, picker }: Props) {
    const [editing, setEditing] = useState<Row | 'new' | null>(null);
    const [archiving, setArchiving] = useState<Row | null>(null);
    const lower = schema.singular.toLowerCase();
    useEffect(() => { if (open === 'create') setEditing('new'); }, [open]);

    const filter = (status: string | null) =>
        router.get(route(`${schema.route}.index`), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const render = (c: ColumnDef, r: Row): ReactNode => {
        const v = r.cells[c.key];
        if (v === null || v === undefined || v === '') return <span className="text-muted">—</span>;
        if (typeof v === 'object') return <span className="pill" style={{ ['--accent' as string]: v.accent ?? 'var(--chart-1)' }}>{v.text}</span>;
        switch (c.type) {
            case 'money': return <span className="tabular">{formatMoney(Number(v), currency ?? '')}</span>;
            case 'number': return <span className="tabular">{formatNumber(Number(v), 0)}</span>;
            case 'date': return <span className="text-muted text-nowrap">{String(v)}</span>;
            case 'title': {
                const sub = r.cells[`${c.key}_sub`];
                const body = (
                    <span className="d-flex align-items-center gap-2">
                        <span className="avatar-tile" style={{ ['--accent' as string]: schema.accent }}>{String(v).slice(0, 2).toUpperCase()}</span>
                        <span className="min-w-0"><span className="fw-semibold d-block text-break">{String(v)}{r.archived && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Archived</span>}</span>
                            {sub ? <small className="text-muted d-block text-truncate" style={{ maxWidth: 360 }}>{String(sub)}</small> : null}</span>
                    </span>
                );
                return schema.show && !r.archived ? <DrawerLink href={route(schema.show, r.id)} className="text-reset text-decoration-none" aria-label={String(v)}>{body}</DrawerLink> : body;
            }
            default: return String(v);
        }
    };

    const columns: Column<Row>[] = [
        ...schema.columns.map((c) => ({ key: c.key, label: c.label, sortable: c.sortable, align: c.align, render: (r: Row) => render(c, r) })),
        {
            key: 'actions', label: '', align: 'end' as const, render: (r: Row) => !schema.canManage ? null : r.archived ? (
                <button type="button" className="btn btn-sm btn-soft" onClick={() => router.post(route(`${schema.route}.restore`, r.id), {}, { preserveScroll: true })}><i className="bi bi-arrow-counterclockwise me-1" />Restore</button>
            ) : (
                <div className="d-inline-flex gap-1">
                    {schema.show && <DrawerLink href={route(schema.show, r.id)} className="btn btn-icon btn-soft" aria-label={String(typeof r.cells.name === 'string' ? r.cells.name : schema.singular)} title="Open"><i className="bi bi-eye" /></DrawerLink>}
                    <button type="button" className="btn btn-icon btn-soft" onClick={() => setEditing(r)} aria-label="Edit" title="Edit"><i className="bi bi-pencil" /></button>
                    <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setArchiving(r)} aria-label="Archive" title={r.locked ?? 'Archive'} disabled={!!r.locked}><i className="bi bi-archive" /></button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={schema.title} />
            <PageHeader icon={schema.icon} title={schema.title} help={schema.help ?? undefined} description={schema.description ?? undefined}
                actions={schema.canManage && <button type="button" className="btn btn-primary" onClick={() => setEditing('new')}><i className="bi bi-plus-lg me-1" />{schema.createLabel ?? `Add ${lower}`}</button>} />

            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: `All`, value: stats.all ?? 0, accent: schema.accent, onClick: () => filter(null), selected: !filters.status },
                ...schema.statuses.map((s) => ({ key: s.key, icon: s.icon, label: s.label, value: stats[s.key] ?? 0, accent: s.accent, onClick: () => filter(s.key), selected: filters.status === s.key })),
                { key: 'archived', icon: 'archive', label: 'Archived', value: stats.archived ?? 0, accent: 'var(--chart-8)', onClick: () => filter('archived'), selected: filters.status === 'archived' },
            ]} />

            {picker && (
                <div className="app-card d-flex flex-wrap align-items-center gap-3 p-3 mb-3">
                    <label htmlFor="resource-picker" className="fw-semibold mb-0">{picker.label}</label>
                    <div style={{ minWidth: 260 }} className="flex-grow-1 flex-md-grow-0">
                        <AsyncSelect inputId="resource-picker" entity={picker.entity} value={picker.value} placeholder={picker.placeholder} isDisabled={!picker.canChange}
                            onChange={(v) => router.post(picker.route, { [picker.field]: v?.value ?? null }, { preserveScroll: true })} />
                    </div>
                    <small className="text-muted">{picker.hint}</small>
                </div>
            )}

            <SectionCard icon="table" title={filters.status === 'archived' ? `Archived ${schema.title.toLowerCase()}` : schema.title} flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder={`Search ${schema.title.toLowerCase()}…`}
                    empty={filters.q || filters.status ? undefined : <EmptyState icon={schema.icon} title={`No ${schema.title.toLowerCase()} yet`}
                        action={schema.canManage && <button type="button" className="btn btn-primary btn-sm" onClick={() => setEditing('new')}>{schema.createLabel ?? `Add ${lower}`}</button>} />} />
            </SectionCard>

            <ResourceForm schema={schema} row={editing} onClose={() => setEditing(null)} />
            <ConfirmDialog open={archiving !== null} danger title={`Archive this ${lower}?`} confirmLabel="Archive"
                message="It disappears from lists and pickers. Everything recorded with it is kept, and you can restore it from the Archived filter."
                onClose={() => setArchiving(null)}
                onConfirm={() => archiving && router.delete(route(`${schema.route}.destroy`, archiving.id), {
                    preserveScroll: true, onFinish: () => setArchiving(null),
                    onError: (e) => toast('error', Object.values(e)[0] ?? 'Could not archive.'),
                })} />
        </>
    );
}

function initial(fields: Field[], row: Row | 'new' | null): Record<string, unknown> {
    const values: Record<string, unknown> = {};
    fields.forEach((f) => { values[f.key] = row && row !== 'new' ? row.form[f.key] ?? (f.type === 'switch' ? false : f.type === 'lookup' ? null : '') : (f.type === 'switch' ? false : f.type === 'lookup' ? null : f.type === 'select' ? f.options?.[0]?.value ?? '' : ''); });
    return values;
}

function ResourceForm({ schema, row, onClose }: { schema: Schema; row: Row | 'new' | null; onClose: () => void }) {
    const { errors } = usePage().props;
    const [values, setValues] = useState<Record<string, unknown>>({});
    const [busy, setBusy] = useState(false);
    useEffect(() => { if (row) setValues(initial(schema.fields, row)); }, [row]); // eslint-disable-line react-hooks/exhaustive-deps
    const isNew = row === 'new';
    const set = (k: string, v: unknown) => setValues((s) => ({ ...s, [k]: v }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const data: Record<string, unknown> = {};
        schema.fields.forEach((f) => { const v = values[f.key]; data[f.key] = f.type === 'lookup' ? (v as Option | null)?.value ?? null : v === '' ? null : v; });
        const opts = { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: onClose };
        if (isNew) router.post(route(`${schema.route}.store`), data as never, opts);
        else if (row) router.put(route(`${schema.route}.update`, row.id), data as never, opts);
    };

    return (
        <SidePanel open={row !== null} onClose={onClose} icon={schema.icon} title={isNew ? (schema.createLabel ?? `Add ${schema.singular.toLowerCase()}`) : `Edit ${schema.singular.toLowerCase()}`}
            footer={<><button type="button" className="btn btn-light" onClick={onClose}>Cancel</button><button type="submit" form="resource-form" className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Save'}</button></>}>
            <form id="resource-form" onSubmit={submit} className="row g-3" noValidate>
                {schema.fields.map((f) => (
                    <div key={f.key} className={`col-md-${f.col ?? 12}`}>
                        <FieldInput field={f} value={values[f.key]} onChange={(v) => set(f.key, v)} error={errors[f.key]} />
                    </div>
                ))}
            </form>
        </SidePanel>
    );
}

export function FieldInput({ field: f, value, onChange, error }: { field: Field; value: unknown; onChange: (v: unknown) => void; error?: string }) {
    const id = `f-${f.key}`;
    const label = <label htmlFor={id} className="form-label">{f.label}{!f.required && f.type !== 'switch' && <span className="text-muted fw-normal ms-1 small">Optional</span>}</label>;
    const hint = f.hint && <div className="form-text">{f.hint}</div>;
    const err = error && <div className="invalid-feedback d-block">{error}</div>;
    const cls = `form-control${error ? ' is-invalid' : ''}`;
    switch (f.type) {
        case 'switch':
            return <div className="form-check form-switch"><input id={id} type="checkbox" className="form-check-input" checked={!!value} onChange={(e) => onChange(e.target.checked)} /><label htmlFor={id} className="form-check-label">{f.label}</label>{hint}{err}</div>;
        case 'textarea':
            return <>{label}<textarea id={id} className={cls} rows={3} value={String(value ?? '')} placeholder={f.placeholder} onChange={(e) => onChange(e.target.value)} />{hint}{err}</>;
        case 'select':
            return <>{label}<select id={id} className={`form-select${error ? ' is-invalid' : ''}`} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>{f.options?.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}</select>{hint}{err}</>;
        case 'lookup':
            return <>{label}<AsyncSelect inputId={id} entity={f.entity ?? ''} value={(value as Option | null) ?? null} onChange={onChange} invalid={!!error} placeholder={f.placeholder ?? 'Search…'} />{hint}{err}</>;
        default:
            return <>{label}<input id={id} className={cls} type={f.type === 'money' || f.type === 'number' ? 'number' : f.type} step={f.type === 'money' ? '0.01' : f.type === 'number' ? 'any' : undefined}
                value={String(value ?? '')} placeholder={f.placeholder} onChange={(e) => onChange(e.target.value)} />{hint}{err}</>;
    }
}
