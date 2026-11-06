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

interface Row { id: number; name: string; address: string | null; phone: string | null; note: string | null; created_at: string | null; archived: boolean }

interface Props {
    rows: Paginated<Row>;
    filters: TableFilters & { status: '' | 'archived' };
    stats: { active: number; archived: number };
    open: 'create' | number | null;
}

export default function Pharmacies({ rows, filters, stats, open }: Props) {
    const [editing, setEditing] = useState<Row | 'new' | null>(null);
    const [archiving, setArchiving] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (open === 'create') setEditing('new');
        else if (typeof open === 'number') setEditing(rows.data.find((r) => r.id === open) ?? null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const filter = (status: '' | 'archived') =>
        router.get(route('pharmacies.index'), { ...filters, status, page: 1 }, { only: ['rows', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const archive = () => {
        if (!archiving) return;
        router.delete(route('pharmacies.destroy', archiving.id), { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => { setBusy(false); setArchiving(null); } });
    };

    const columns: Column<Row>[] = [
        {
            key: 'name', label: 'Pharmacy', sortable: true,
            render: (r) => (
                <span className="d-flex align-items-center gap-2">
                    <span className="avatar-tile" style={{ ['--accent' as string]: `var(--chart-${(r.id % 8) + 1})` }}><i className="bi bi-hospital" /></span>
                    <span>
                        <span className="fw-semibold d-block">{r.name}</span>
                        {r.address && <small className="text-muted">{r.address}</small>}
                    </span>
                </span>
            ),
        },
        { key: 'phone', label: 'Phone', sortable: true, render: (r) => r.phone ?? <span className="text-muted">—</span> },
        { key: 'note', label: 'Note', render: (r) => <span className="text-muted small">{r.note ?? ''}</span> },
        { key: 'created_at', label: 'Added', sortable: true, render: (r) => <span className="text-muted">{r.created_at}</span> },
        {
            key: 'actions', label: '', align: 'end',
            render: (r) => r.archived ? (
                <button type="button" className="btn btn-sm btn-soft" onClick={() => router.post(route('pharmacies.restore', r.id), {}, { preserveScroll: true })}>
                    <i className="bi bi-arrow-counterclockwise me-1" />Restore
                </button>
            ) : (
                <div className="d-inline-flex gap-1">
                    <button type="button" className="btn btn-icon btn-soft" onClick={() => setEditing(r)} title={`Edit ${r.name}`} aria-label={`Edit ${r.name}`}><i className="bi bi-pencil" /></button>
                    <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setArchiving(r)} title={`Archive ${r.name}`} aria-label={`Archive ${r.name}`}><i className="bi bi-archive" /></button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Pharmacies" />
            <PageHeader
                icon="hospital"
                title="Pharmacies"
                description="The pharmacies (branches) of this business, with their address and phone."
                actions={<button type="button" className="btn btn-primary" onClick={() => setEditing('new')}><i className="bi bi-plus-lg me-1" />Add pharmacy</button>}
            />
            <StatStrip
                stats={[
                    { key: 'active', icon: 'hospital', label: 'Pharmacies', value: stats.active, accent: 'var(--chart-5)', onClick: () => filter(''), selected: filters.status !== 'archived' },
                    { key: 'archived', icon: 'archive', label: 'Archived', value: stats.archived, accent: 'var(--chart-8)', onClick: () => filter('archived'), selected: filters.status === 'archived' },
                ]}
            />
            <SectionCard icon="table" title={filters.status === 'archived' ? 'Archived pharmacies' : 'All pharmacies'} subtitle="Search by name, address or phone" flush>
                <DataTable
                    page={rows}
                    columns={columns}
                    filters={filters}
                    only={['rows', 'filters']}
                    searchPlaceholder="Search pharmacies…"
                    empty={filters.q || filters.status ? undefined : (
                        <EmptyState icon="hospital" title="No pharmacies yet" hint="Add each branch with its address and phone."
                            action={<button type="button" className="btn btn-primary btn-sm" onClick={() => setEditing('new')}>Add your first pharmacy</button>} />
                    )}
                />
            </SectionCard>
            <PharmacyModal row={editing} onClose={() => setEditing(null)} />
            <ConfirmDialog open={archiving !== null} title="Archive pharmacy?" message={archiving ? `“${archiving.name}” moves to Archived; you can restore it later.` : ''}
                confirmLabel="Archive" danger busy={busy} onConfirm={archive} onClose={() => setArchiving(null)} />
        </>
    );
}

function PharmacyModal({ row, onClose }: { row: Row | 'new' | null; onClose: () => void }) {
    const isNew = row === 'new';
    const form = useForm({ name: '', address: '', phone: '', note: '' });

    useEffect(() => {
        form.clearErrors();
        if (row === 'new') form.setData({ name: '', address: '', phone: '', note: '' });
        else if (row) form.setData({ name: row.name, address: row.address ?? '', phone: row.phone ?? '', note: row.note ?? '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [row]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };
        if (isNew) form.post(route('pharmacies.store'), options);
        else if (row) form.put(route('pharmacies.update', row.id), options);
    };
    const field = (key: 'name' | 'address' | 'phone' | 'note', label: string, max: number, placeholder = '') => (
        <div className="mb-3">
            <label className="form-label fw-semibold" htmlFor={`ph-${key}`}>{label}</label>
            <input id={`ph-${key}`} className={`form-control ${form.errors[key] ? 'is-invalid' : ''}`} value={form.data[key]} maxLength={max} placeholder={placeholder}
                onChange={(e) => form.setData(key, e.target.value)} autoFocus={key === 'name'} />
            {form.errors[key] && <div className="invalid-feedback">{form.errors[key]}</div>}
        </div>
    );

    return (
        <Modal open={row !== null} title={isNew ? 'Add pharmacy' : 'Edit pharmacy'} icon="hospital" onClose={onClose}
            footer={
                <>
                    <button type="button" className="btn btn-light" onClick={onClose}>Cancel</button>
                    <button type="submit" form="pharmacy-form" className="btn btn-primary" disabled={form.processing}>
                        {form.processing && <span className="spinner-border spinner-border-sm me-2" />}{isNew ? 'Add pharmacy' : 'Save changes'}
                    </button>
                </>
            }>
            <form id="pharmacy-form" onSubmit={submit} noValidate>
                {field('name', 'Name', 191, 'e.g. Main Road branch')}
                {field('address', 'Address', 255)}
                <div className="row g-2">
                    <div className="col-sm-6">{field('phone', 'Phone', 50)}</div>
                    <div className="col-sm-6">{field('note', 'Note', 150)}</div>
                </div>
            </form>
        </Modal>
    );
}
