import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';

interface RoleRow { id: number; name: string; description: string | null; system_key: string | null; limits: { max_discount_percent?: number }; permissions: string[]; members: number }
interface CatalogueModule { module: string; name: string; active: boolean; permissions: { key: string; label: string; description: string }[] }

export default function Roles({ roles, filters, catalogue }: { roles: Paginated<RoleRow>; filters: TableFilters; catalogue: CatalogueModule[] }) {
    const [editing, setEditing] = useState<RoleRow | 'new' | null>(null);
    const [archiving, setArchiving] = useState<RoleRow | null>(null);
    const total = catalogue.reduce((n, m) => n + m.permissions.length, 0);

    const columns: Column<RoleRow>[] = [
        {
            key: 'name', label: 'Role', render: (r) => (
                <div>
                    <strong>{r.name}</strong>
                    {r.system_key && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Built-in</span>}
                    {r.description && <div className="small text-muted">{r.description}</div>}
                </div>
            ),
        },
        { key: 'permissions', label: 'Permissions', render: (r) => (r.permissions.includes('*') ? <span className="pill" style={{ ['--accent' as string]: 'var(--app-success)' }}>Everything</span> : <span className="tabular">{r.permissions.length} of {total}</span>) },
        { key: 'members', label: 'Members', align: 'end', className: 'tabular', render: (r) => r.members },
        {
            key: 'actions', label: '', align: 'end', render: (r) => (
                <div className="d-inline-flex gap-1">
                    <button type="button" className="btn btn-icon btn-soft" onClick={() => setEditing(r)} aria-label={`${r.system_key === 'owner' ? 'View' : 'Edit'} ${r.name}`}><i className={`bi bi-${r.system_key === 'owner' ? 'eye' : 'pencil'}`} /></button>
                    {!r.system_key && <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setArchiving(r)} aria-label={`Archive ${r.name}`}><i className="bi bi-archive" /></button>}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Roles" />
            <PageHeader icon="shield-lock" title="Roles" description="What each person can do in this business. Permissions come from your active modules."
                actions={<button type="button" className="btn btn-primary" onClick={() => setEditing('new')}><i className="bi bi-plus-lg me-1" />New role</button>} />
            <SectionCard icon="people" title="Roles in this business" flush>
                <DataTable page={roles} columns={columns} filters={filters} only={['roles', 'filters']} searchPlaceholder="Search roles…" />
            </SectionCard>
            <RoleEditor role={editing} catalogue={catalogue} onClose={() => setEditing(null)} />
            <ConfirmDialog open={archiving !== null} title="Archive role?" danger confirmLabel="Archive"
                message={`“${archiving?.name}” will no longer be available.`}
                onConfirm={() => archiving && router.delete(route('settings.roles.destroy', archiving.id), { preserveScroll: true, onFinish: () => setArchiving(null) })}
                onClose={() => setArchiving(null)} />
        </>
    );
}

function RoleEditor({ role, catalogue, onClose }: { role: RoleRow | 'new' | null; catalogue: CatalogueModule[]; onClose: () => void }) {
    const readOnly = role !== 'new' && role?.system_key === 'owner';
    const builtIn = role !== 'new' && !!role?.system_key;
    const form = useForm({ name: '', description: '', permissions: [] as string[], limits: { max_discount_percent: '' as number | '' } });
    const [q, setQ] = useState('');

    useEffect(() => {
        form.clearErrors();
        setQ('');
        if (role === 'new') form.setData({ name: '', description: '', permissions: [], limits: { max_discount_percent: '' } });
        else if (role) form.setData({ name: role.name, description: role.description ?? '', permissions: role.permissions.includes('*') ? catalogue.flatMap((m) => m.permissions.map((p) => p.key)) : role.permissions, limits: { max_discount_percent: role.limits?.max_discount_percent ?? '' } });
    }, [role]); // eslint-disable-line react-hooks/exhaustive-deps

    const has = (key: string) => form.data.permissions.includes(key);
    const toggle = (key: string) => !readOnly && form.setData('permissions', has(key) ? form.data.permissions.filter((k) => k !== key) : [...form.data.permissions, key]);
    const toggleModule = (m: CatalogueModule, on: boolean) => {
        if (readOnly) return;
        const keys = m.permissions.map((p) => p.key);
        form.setData('permissions', on ? Array.from(new Set([...form.data.permissions, ...keys])) : form.data.permissions.filter((k) => !keys.includes(k)));
    };
    const shown = useMemo(() => catalogue.map((m) => ({ ...m, permissions: m.permissions.filter((p) => `${p.label} ${p.description}`.toLowerCase().includes(q.trim().toLowerCase())) })).filter((m) => m.permissions.length), [catalogue, q]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => onClose() };
        if (role === 'new') form.post(route('settings.roles.store'), opts);
        else if (role) form.put(route('settings.roles.update', role.id), opts);
    };

    return (
        <Modal open={role !== null} title={role === 'new' ? 'New role' : readOnly ? 'Owner (everything)' : `Edit ${role?.name}`} icon="shield-lock" onClose={onClose} width={860}
            footer={readOnly ? <button type="button" className="btn btn-light" onClick={onClose}>Close</button> : <><button type="button" className="btn btn-light" onClick={onClose}>Cancel</button><button type="submit" form="role-form" className="btn btn-primary" disabled={form.processing}>Save role</button></>}>
            <form id="role-form" onSubmit={submit} noValidate>
                <div className="row g-3 mb-3">
                    <div className="col-md-5">
                        <label className="form-label fw-semibold" htmlFor="role-name">Name</label>
                        <input id="role-name" className={`form-control ${form.errors.name ? 'is-invalid' : ''}`} value={form.data.name} disabled={builtIn} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. Store manager" />
                        {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                    </div>
                    <div className="col-md-4">
                        <label className="form-label fw-semibold" htmlFor="role-desc">Description</label>
                        <input id="role-desc" className="form-control" value={form.data.description} disabled={readOnly} onChange={(e) => form.setData('description', e.target.value)} />
                    </div>
                    <div className="col-md-3">
                        <label className="form-label fw-semibold" htmlFor="role-disc">Max discount %</label>
                        <input id="role-disc" type="number" min={0} max={100} className={`form-control ${form.errors['limits.max_discount_percent'] ? 'is-invalid' : ''}`} value={form.data.limits.max_discount_percent} disabled={readOnly}
                            onChange={(e) => form.setData('limits', { max_discount_percent: e.target.value === '' ? '' : Number(e.target.value) })} placeholder="No limit" />
                    </div>
                </div>

                <div className="d-flex align-items-center gap-2 mb-2">
                    <div className="quick-search flex-grow-1" style={{ maxWidth: 320 }}>
                        <i className="bi bi-search" />
                        <input type="search" className="form-control form-control-sm" placeholder="Find a permission…" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Find a permission" />
                    </div>
                    <span className="small text-muted ms-auto">{form.data.permissions.length} selected</span>
                </div>

                <div className="d-flex flex-column gap-3">
                    {shown.map((m) => {
                        const all = m.permissions.every((p) => has(p.key));
                        return (
                            <div key={m.module} className="app-card p-3" style={{ opacity: m.active ? 1 : 0.6 }}>
                                <div className="d-flex align-items-center mb-2">
                                    <strong>{m.name}</strong>
                                    {!m.active && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--chart-8)' }}>Module off: no effect</span>}
                                    {!readOnly && (
                                        <button type="button" className="btn btn-sm btn-link ms-auto" onClick={() => toggleModule(m, !all)}>{all ? 'Clear all' : 'Select all'}</button>
                                    )}
                                </div>
                                <div className="row g-2">
                                    {m.permissions.map((p) => (
                                        <div className="col-md-6" key={p.key}>
                                            <label className="d-flex gap-2 align-items-start p-2 rounded" style={{ cursor: readOnly ? 'default' : 'pointer', background: has(p.key) ? 'var(--app-primary-soft)' : 'transparent' }}>
                                                <span className="form-check form-switch m-0">
                                                    <input className="form-check-input" type="checkbox" role="switch" checked={has(p.key)} disabled={readOnly} onChange={() => toggle(p.key)} aria-label={p.label} />
                                                </span>
                                                <span>
                                                    <span className="fw-semibold d-block">{p.label}</span>
                                                    <small className="text-muted">{p.description}</small>
                                                </span>
                                            </label>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>
                {form.errors.permissions && <div className="text-danger small mt-2">{form.errors.permissions}</div>}
            </form>
        </Modal>
    );
}
