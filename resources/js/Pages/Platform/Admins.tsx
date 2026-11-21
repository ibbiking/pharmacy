import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import type { Paginated } from '@/types';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import ConfirmDialog from '@/Components/ConfirmDialog';
import PasswordInput from '@/Components/PasswordInput';

interface Admin { id: number; name: string; email: string; active: boolean; last_login_at: string | null; me: boolean }

/** Who can sign in to the platform portal. */
export default function PlatformAdmins({ admins }: { admins: Paginated<Admin> }) {
    const form = useForm({ name: '', email: '', password: '' });
    const [archiving, setArchiving] = useState<Admin | null>(null);
    const add = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('platform.admins.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <>
            <Head title="Platform admins" />
            <PageHeader icon="person-lock" title="Platform admins" description="People who can sign in to this platform portal. They are separate from business logins." />
            <div className="row g-3">
                <div className="col-lg-8">
                    <SectionCard icon="people" title="Admins" flush>
                        <div className="table-responsive">
                            <table className="table align-middle mb-0">
                                <thead><tr><th>Name</th><th>Email</th><th>Last sign-in</th><th>Status</th><th className="text-end">Actions</th></tr></thead>
                                <tbody>
                                    {admins.data.map((a) => (
                                        <tr key={a.id}>
                                            <td className="fw-semibold">{a.name}{a.me && <span className="badge text-bg-light ms-2">You</span>}</td>
                                            <td>{a.email}</td>
                                            <td className="text-muted">{a.last_login_at ?? 'Never'}</td>
                                            <td>{a.active ? <span className="badge text-bg-success">Active</span> : <span className="badge text-bg-secondary">Off</span>}</td>
                                            <td className="text-end">
                                                {!a.me && (
                                                    <>
                                                        <button type="button" className="btn btn-sm btn-light me-1" onClick={() => router.post(route('platform.admins.toggle', a.id), {}, { preserveScroll: true })}>
                                                            {a.active ? 'Switch off' : 'Switch on'}
                                                        </button>
                                                        <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => setArchiving(a)}>Archive</button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </SectionCard>
                </div>
                <div className="col-lg-4">
                    <SectionCard icon="person-plus" title="Add a platform admin">
                        <form onSubmit={add} className="d-grid gap-3" noValidate>
                            <div>
                                <label className="form-label fw-semibold mb-1" htmlFor="pa-name">Name</label>
                                <input id="pa-name" className={`form-control ${form.errors.name ? 'is-invalid' : ''}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                            </div>
                            <div>
                                <label className="form-label fw-semibold mb-1" htmlFor="pa-email">Email</label>
                                <input id="pa-email" type="email" className={`form-control ${form.errors.email ? 'is-invalid' : ''}`} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                {form.errors.email && <div className="invalid-feedback">{form.errors.email}</div>}
                            </div>
                            <PasswordInput label="Password" value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />
                            <button type="submit" className="btn btn-primary" disabled={form.processing}>Add admin</button>
                        </form>
                    </SectionCard>
                </div>
            </div>
            <ConfirmDialog open={!!archiving} title="Archive this platform admin?" confirmLabel="Archive"
                message={archiving ? `${archiving.name} will no longer be able to sign in to the platform.` : ''}
                onConfirm={() => archiving && router.delete(route('platform.admins.destroy', archiving.id), { preserveScroll: true, onFinish: () => setArchiving(null) })}
                onClose={() => setArchiving(null)} />
        </>
    );
}
