import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';

type Status = 'used' | 'unused' | 'archived' | null;
interface Row { id: number; name: string; description: string | null; usage: number; created_at: string | null; archived: boolean }
interface Entity { key: string; singular: string; plural: string; products: string; icon: string; accent: string; max: number; help: string | null; examples: string; canManage: boolean }
interface Props {
    entity: Entity;
    rows: Paginated<Row>;
    filters: TableFilters & { status?: Status };
    stats: { total: number; used: number; unused: number; archived: number; added_30d: number };
    open: 'create' | number | null;
}

/** One screen for every simple master-data list (manufacturers, dosage forms, strengths, formulas…). */
export default function MasterDataIndex({ entity, rows, filters, stats, open }: Props) {
    const [editing, setEditing] = useState<Row | 'new' | null>(null);
    const [archiving, setArchiving] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);
    const archived = filters.status === 'archived';
    const lower = entity.singular.toLowerCase();

    useEffect(() => {
        if (open === 'create') setEditing('new');
        else if (typeof open === 'number') setEditing(rows.data.find((r) => r.id === open) ?? null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const filter = (status: Status) =>
        router.get(route(`${entity.key}.index`), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const restore = (r: Row) => router.post(route(`${entity.key}.restore`, r.id), {}, { preserveScroll: true, only: ['rows', 'stats', 'flash'] });

    const columns: Column<Row>[] = [
        {
            key: 'name', label: entity.singular, sortable: true, render: (r) => (
                <div className="d-flex align-items-center gap-2">
                    <span className="avatar-tile" style={{ ['--accent' as string]: entity.accent }}>{r.name.slice(0, 2).toUpperCase()}</span>
                    <div className="min-w-0">
                        <div className="fw-semibold text-break">{r.name}{r.archived && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Archived</span>}</div>
                        {r.description && <div className="small text-muted text-truncate" style={{ maxWidth: 420 }}>{r.description}</div>}
                    </div>
                </div>
            ),
        },
        {
            key: 'usage', label: `Used by ${entity.products.toLowerCase()}`, sortable: true, align: 'end', className: 'tabular',
            render: (r) => (r.usage > 0 ? <span className="pill" style={{ ['--accent' as string]: 'var(--app-success)' }}>{r.usage}</span> : <span className="text-muted">—</span>),
        },
        { key: 'created_at', label: 'Added', sortable: true, render: (r) => <span className="text-muted">{r.created_at}</span> },
        {
            key: 'actions', label: '', align: 'end', render: (r) => !entity.canManage ? null : r.archived ? (
                <button type="button" className="btn btn-sm btn-soft" onClick={() => restore(r)} aria-label={`Restore ${r.name}`}><i className="bi bi-arrow-counterclockwise me-1" />Restore</button>
            ) : (
                <div className="d-inline-flex gap-1">
                    <button type="button" className="btn btn-icon btn-soft" onClick={() => setEditing(r)} aria-label={`Edit ${r.name}`} title="Edit"><i className="bi bi-pencil" /></button>
                    <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setArchiving(r)} aria-label={`Archive ${r.name}`}
                        title={r.usage ? `Used by ${r.usage} ${entity.products.toLowerCase()}` : 'Archive'}><i className="bi bi-archive" /></button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={entity.plural} />
            <PageHeader icon={entity.icon} title={entity.plural} help={entity.help ?? undefined}
                description={`The ${entity.plural.toLowerCase()} you pick on ${entity.products.toLowerCase()}. ${entity.examples}.`}
                actions={entity.canManage && <button type="button" className="btn btn-primary" onClick={() => setEditing('new')}><i className="bi bi-plus-lg me-1" />Add {lower}</button>} />

            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: `All ${entity.plural.toLowerCase()}`, value: stats.total, accent: entity.accent, onClick: () => filter(null), selected: !filters.status },
                { key: 'used', icon: 'check2-circle', label: 'In use', value: stats.used, accent: 'var(--app-success)', onClick: () => filter('used'), selected: filters.status === 'used' },
                { key: 'unused', icon: 'circle', label: 'Not used yet', value: stats.unused, accent: 'var(--chart-8)', onClick: () => filter('unused'), selected: filters.status === 'unused' },
                { key: 'archived', icon: 'archive', label: 'Archived', value: stats.archived, accent: 'var(--app-warning)', onClick: () => filter('archived'), selected: archived },
                { key: 'new', icon: 'stars', label: 'Added (30 days)', value: stats.added_30d, accent: 'var(--chart-2)' },
            ]} />

            {archived && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-warning)' }}>
                    <i className="bi bi-archive" />
                    <div><strong>Archived {entity.plural.toLowerCase()}</strong><p>They can’t be picked on {entity.products.toLowerCase()}. Restore one to use it again.</p></div>
                </div>
            )}

            <SectionCard icon="table" title={archived ? `Archived ${entity.plural.toLowerCase()}` : `All ${entity.plural.toLowerCase()}`} subtitle="Search, sort and page through the list" flush>
                <DataTable page={rows} columns={columns} filters={filters} only={['rows', 'filters']} searchPlaceholder={`Search ${entity.plural.toLowerCase()}…`}
                    empty={filters.q || filters.status ? undefined : (
                        <EmptyState icon={entity.icon} title={`No ${entity.plural.toLowerCase()} yet`} hint={entity.examples}
                            action={entity.canManage && <button type="button" className="btn btn-primary btn-sm" onClick={() => setEditing('new')}>Add your first {lower}</button>} />
                    )} />
            </SectionCard>

            <FormModal entity={entity} row={editing} onClose={() => setEditing(null)} />
            <ConfirmDialog open={archiving !== null} title={`Archive ${lower}?`} danger confirmLabel="Archive" busy={busy}
                message={archiving ? (archiving.usage
                    ? `“${archiving.name}” is used by ${archiving.usage} ${entity.products.toLowerCase()}, so it can’t be archived yet. Change those ${entity.products.toLowerCase()} first.`
                    : `“${archiving.name}” will be hidden from lists and pickers. You can restore it from the Archived filter.`) : ''}
                onConfirm={() => archiving && router.delete(route(`${entity.key}.destroy`, archiving.id), {
                    preserveScroll: true, onStart: () => setBusy(true), onFinish: () => { setBusy(false); setArchiving(null); },
                })}
                onClose={() => setArchiving(null)} />
        </>
    );
}

function FormModal({ entity, row, onClose }: { entity: Entity; row: Row | 'new' | null; onClose: () => void }) {
    const isNew = row === 'new';
    const form = useForm({ name: '', description: '' });

    useEffect(() => {
        form.clearErrors();
        if (row === 'new') form.setData({ name: '', description: '' });
        else if (row) form.setData({ name: row.name, description: row.description ?? '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [row]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => onClose() };
        if (isNew) form.post(route(`${entity.key}.store`), opts);
        else if (row) form.put(route(`${entity.key}.update`, row.id), opts);
    };

    return (
        <Modal open={row !== null} onClose={onClose} icon={entity.icon} title={isNew ? `Add ${entity.singular.toLowerCase()}` : `Edit ${entity.singular.toLowerCase()}`}
            footer={<>
                <button type="button" className="btn btn-light" onClick={onClose}>Cancel</button>
                <button type="submit" form="md-form" className="btn btn-primary" disabled={form.processing}>{form.processing && <span className="spinner-border spinner-border-sm me-2" />}{isNew ? 'Add' : 'Save'}</button>
            </>}>
            <form id="md-form" onSubmit={submit} noValidate className="d-grid gap-3">
                <div>
                    <label className="form-label fw-semibold" htmlFor="md-name">Name</label>
                    <input id="md-name" className={`form-control ${form.errors.name ? 'is-invalid' : ''}`} value={form.data.name} maxLength={entity.max} autoFocus
                        onChange={(e) => form.setData('name', e.target.value)} placeholder={entity.examples.replace(/^e\.g\. /, '').split(',')[0]} />
                    {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                </div>
                <div>
                    <label className="form-label fw-semibold" htmlFor="md-desc">Description <small className="text-muted fw-normal">Optional</small></label>
                    <textarea id="md-desc" rows={3} className={`form-control ${form.errors.description ? 'is-invalid' : ''}`} value={form.data.description} maxLength={255}
                        onChange={(e) => form.setData('description', e.target.value)} />
                    <div className="d-flex justify-content-between">
                        {form.errors.description ? <div className="invalid-feedback d-block">{form.errors.description}</div> : <span />}
                        <small className="text-muted">{form.data.description.length}/255</small>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
