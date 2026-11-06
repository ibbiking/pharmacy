import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import PasswordInput from '@/Components/PasswordInput';
import SectionCard from '@/Components/SectionCard';

interface Account { name: string; email: string; avatar: string | null; since: string | null; verified: boolean; email_locked: boolean }

const initials = (name: string) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]!.toUpperCase()).join('') || '?';

export default function Profile({ account }: { account: Account }) {
    return (
        <>
            <Head title="My profile" />
            <PageHeader icon="person-circle" title="My profile" description="Your own sign-in details. They are the same in every business you belong to." />
            <div className="row g-4">
                <div className="col-lg-7"><Details account={account} /></div>
                <div className="col-lg-5"><Password /></div>
            </div>
        </>
    );
}

function Details({ account }: { account: Account }) {
    const form = useForm<{ name: string; email: string; current_password: string; avatar: File | null; remove_avatar: boolean }>({
        name: account.name, email: account.email, current_password: '', avatar: null, remove_avatar: false,
    });
    const [preview, setPreview] = useState<string | null>(account.avatar);
    const fileRef = useRef<HTMLInputElement>(null);
    useEffect(() => setPreview(account.avatar), [account.avatar]);
    const emailChanged = form.data.email.trim().toLowerCase() !== account.email.toLowerCase();

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('profile.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { form.reset('current_password', 'avatar', 'remove_avatar'); if (fileRef.current) fileRef.current.value = ''; },
        });
    };
    const showImage = preview && !form.data.remove_avatar;

    return (
        <SectionCard icon="person" title="Your details" subtitle={account.since ? `Account since ${account.since}` : undefined}>
            <form onSubmit={submit} noValidate>
                <div className="d-flex align-items-center gap-3 mb-4">
                    {showImage ? (
                        <img src={preview!} alt="" style={{ width: 64, height: 64, borderRadius: '50%', objectFit: 'cover', border: '1px solid var(--app-border)' }} />
                    ) : (
                        <span className="avatar-initials" style={{ width: 64, height: 64, fontSize: '1.3rem', background: 'linear-gradient(135deg, var(--chart-4), var(--chart-7))' }}>{initials(form.data.name)}</span>
                    )}
                    <div className="flex-grow-1">
                        <label className="form-label fw-semibold mb-1" htmlFor="avatar">Photo <small className="text-muted fw-normal">JPG / PNG / WebP, max 2 MB</small></label>
                        <input ref={fileRef} id="avatar" type="file" accept="image/png,image/jpeg,image/webp" className={`form-control ${form.errors.avatar ? 'is-invalid' : ''}`}
                            onChange={(e) => { const f = e.target.files?.[0] ?? null; form.setData((d) => ({ ...d, avatar: f, remove_avatar: false })); if (f) setPreview(URL.createObjectURL(f)); }} />
                        {form.errors.avatar && <div className="invalid-feedback">{form.errors.avatar}</div>}
                        {account.avatar && (
                            <div className="form-check mt-2">
                                <input className="form-check-input" type="checkbox" id="remove-avatar" checked={form.data.remove_avatar} onChange={(e) => form.setData('remove_avatar', e.target.checked)} />
                                <label className="form-check-label small" htmlFor="remove-avatar">Remove photo</label>
                            </div>
                        )}
                    </div>
                </div>

                <div className="mb-3">
                    <label className="form-label fw-semibold" htmlFor="name">Name</label>
                    <input id="name" className={`form-control ${form.errors.name ? 'is-invalid' : ''}`} value={form.data.name} maxLength={200} autoComplete="name" onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                </div>

                <div className="mb-3">
                    <label className="form-label fw-semibold" htmlFor="email">
                        Email {account.verified && <span className="badge ms-1 fw-normal" style={{ background: 'var(--app-success-soft)', color: 'var(--app-success)' }}>verified</span>}
                    </label>
                    <input id="email" type="email" className={`form-control ${form.errors.email ? 'is-invalid' : ''}`} value={form.data.email} maxLength={191} autoComplete="email"
                        readOnly={account.email_locked} onChange={(e) => form.setData('email', e.target.value)} />
                    {form.errors.email && <div className="invalid-feedback">{form.errors.email}</div>}
                    <div className="form-text">
                        {account.email_locked ? 'This is the email the business invited you with. Ask the owner to change it.' : 'You sign in with this email, and password-reset codes go to it.'}
                    </div>
                </div>

                {emailChanged && !account.email_locked && (
                    <div className="mb-3 p-3 rounded" style={{ background: 'var(--app-surface-muted)', border: '1px solid var(--app-border)' }}>
                        <PasswordInput label="Current password (needed to change your email)" value={form.data.current_password} name="current_password"
                            onChange={(v) => form.setData('current_password', v)} error={form.errors.current_password} />
                    </div>
                )}

                <div className="d-flex">
                    <button type="submit" className="btn btn-primary ms-auto" disabled={form.processing || !form.isDirty}>Save profile</button>
                </div>
            </form>
        </SectionCard>
    );
}

function Password() {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('update-password'), { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const mismatch = form.data.password_confirmation !== '' && form.data.password !== form.data.password_confirmation;

    return (
        <SectionCard icon="shield-lock" title="Password" subtitle="At least 8 characters, with letters and numbers">
            <form onSubmit={submit} noValidate className="d-grid gap-3">
                <PasswordInput label="Current password" name="current_password" value={form.data.current_password} onChange={(v) => form.setData('current_password', v)} error={form.errors.current_password} />
                <PasswordInput label="New password" name="password" autoComplete="new-password" meter value={form.data.password} onChange={(v) => form.setData('password', v)} error={form.errors.password} />
                <PasswordInput label="Repeat new password" name="password_confirmation" autoComplete="new-password" value={form.data.password_confirmation}
                    onChange={(v) => form.setData('password_confirmation', v)} error={mismatch ? 'The two passwords don’t match yet.' : form.errors.password_confirmation} />
                <div className="d-flex">
                    <span className="small text-muted align-self-center">You stay signed in on this device.</span>
                    <button type="submit" className="btn btn-primary ms-auto" disabled={form.processing || !form.data.current_password || !form.data.password || mismatch}>Change password</button>
                </div>
            </form>
        </SectionCard>
    );
}
