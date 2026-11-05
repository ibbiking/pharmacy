import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import StatStrip from '@/Components/StatStrip';
import DataTable, { type Column } from '@/Components/DataTable';
import Modal from '@/Components/Modal';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { initials } from '@/lib/format';

interface Member { id: number; name: string; email: string; status: 'active' | 'suspended'; role_id: number | null; role_name: string | null; is_owner: boolean; joined: string | null; is_me: boolean }
interface Role { id: number; name: string; system_key: string | null }

export default function Team({ members, filters, roles, stats }: { members: Paginated<Member>; filters: TableFilters; roles: Role[]; stats: { total: number; active: number; suspended: number } }) {
    const [inviting, setInviting] = useState(false);
    const [removing, setRemoving] = useState<Member | null>(null);

    const update = (m: Member, data: { role_id?: number; status?: string }) => router.put(route('settings.team.update', m.id), data, { preserveScroll: true });

    const columns: Column<Member>[] = [
        {
            key: 'name', label: 'Member', render: (m) => (
                <span className="d-flex align-items-center gap-2">
                    <span className="avatar-initials" style={{ width: 32, height: 32, fontSize: '0.75rem' }}>{initials(m.name)}</span>
                    <span><strong>{m.name}</strong>{m.is_me && <span className="pill ms-2">You</span>}<small className="d-block text-muted">{m.email}</small></span>
                </span>
            ),
        },
        {
            key: 'role', label: 'Role', render: (m) => m.is_me ? <span>{m.role_name}</span> : (
                <select className="form-select form-select-sm" style={{ maxWidth: 200 }} value={m.role_id ?? ''} onChange={(e) => update(m, { role_id: Number(e.target.value) })} aria-label={`Role of ${m.name}`}>
                    {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                </select>
            ),
        },
        { key: 'status', label: 'Status', render: (m) => <span className="pill" style={{ ['--accent' as string]: m.status === 'active' ? 'var(--app-success)' : 'var(--app-warning)' }}>{m.status === 'active' ? 'Active' : 'Suspended'}</span> },
        { key: 'joined', label: 'Joined', render: (m) => <span className="text-muted">{m.joined}</span> },
        {
            key: 'actions', label: '', align: 'end', render: (m) => m.is_me ? null : (
                <div className="d-inline-flex gap-1">
                    <button type="button" className="btn btn-sm btn-light" onClick={() => update(m, { status: m.status === 'active' ? 'suspended' : 'active' })}>
                        {m.status === 'active' ? 'Suspend' : 'Reactivate'}
                    </button>
                    <button type="button" className="btn btn-icon btn-outline-danger" onClick={() => setRemoving(m)} aria-label={`Remove ${m.name}`}><i className="bi bi-person-dash" /></button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Team" />
            <PageHeader icon="people" title="Team" description="People who work in this business and their roles. Roles decide what each person can do."
                actions={<button type="button" className="btn btn-primary" onClick={() => setInviting(true)}><i className="bi bi-person-plus me-1" />Add person</button>} />
            <StatStrip stats={[
                { key: 'total', icon: 'people', label: 'Members', value: stats.total, accent: 'var(--chart-1)' },
                { key: 'active', icon: 'check2-circle', label: 'Active', value: stats.active, accent: 'var(--app-success)' },
                { key: 'suspended', icon: 'pause-circle', label: 'Suspended', value: stats.suspended, accent: 'var(--app-warning)' },
            ]} />
            <SectionCard icon="person-badge" title="Members" flush>
                <DataTable page={members} columns={columns} filters={filters} only={['members', 'filters', 'stats']} searchPlaceholder="Search by name or email…" />
            </SectionCard>
            <InviteModal open={inviting} roles={roles} onClose={() => setInviting(false)} />
            <ConfirmDialog open={removing !== null} title="Remove from team?" danger confirmLabel="Remove"
                message={`${removing?.name} will lose access to this business. Their past sales and history stay.`}
                onConfirm={() => removing && router.delete(route('settings.team.remove', removing.id), { preserveScroll: true, onFinish: () => setRemoving(null) })}
                onClose={() => setRemoving(null)} />
        </>
    );
}

function InviteModal({ open, roles, onClose }: { open: boolean; roles: Role[]; onClose: () => void }) {
    const defaultRole = roles.find((r) => r.system_key === 'sales-person')?.id ?? roles[0]?.id ?? 0;
    const form = useForm({ name: '', email: '', role_id: defaultRole });
    const submit = (e: FormEvent) => { e.preventDefault(); form.post(route('settings.team.invite'), { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } }); };

    return (
        <Modal open={open} title="Add a person" icon="person-plus" onClose={onClose}
            footer={<><button type="button" className="btn btn-light" onClick={onClose}>Cancel</button><button type="submit" form="invite-form" className="btn btn-primary" disabled={form.processing}>Add & send invitation</button></>}>
            <form id="invite-form" onSubmit={submit} noValidate className="d-flex flex-column gap-3">
                <div>
                    <label className="form-label fw-semibold" htmlFor="inv-name">Name</label>
                    <input id="inv-name" className={`form-control ${form.errors.name ? 'is-invalid' : ''}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                </div>
                <div>
                    <label className="form-label fw-semibold" htmlFor="inv-email">Email</label>
                    <input id="inv-email" type="email" className={`form-control ${form.errors.email ? 'is-invalid' : ''}`} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                    {form.errors.email && <div className="invalid-feedback">{form.errors.email}</div>}
                </div>
                <div>
                    <label className="form-label fw-semibold" htmlFor="inv-role">Role</label>
                    <select id="inv-role" className="form-select" value={form.data.role_id} onChange={(e) => form.setData('role_id', Number(e.target.value))}>
                        {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                    </select>
                </div>
                <p className="small text-muted mb-0">They get an email with a sign-in link. New people set their own password with “Forgot password”: no password is ever sent by email.</p>
            </form>
        </Modal>
    );
}
