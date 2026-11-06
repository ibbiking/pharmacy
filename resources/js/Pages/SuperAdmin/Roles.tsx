import { Head, Link } from '@inertiajs/react';
import type { Paginated } from '@/types';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';

interface Row { id: number; name: string; users: number; permissions: number; sample: string[] }

export default function Roles({ roles }: { roles: Paginated<Row> }) {
    return (
        <>
            <Head title="Global roles" />
            <PageHeader icon="shield-check" title="Global roles" description="Platform-wide roles (shared by every business). Read only: what someone may do inside a business comes from that business's own roles." />
            <div className="alert alert-info d-flex gap-2 align-items-start">
                <i className="bi bi-info-circle-fill mt-1" />
                <div className="small">
                    <strong>super-admin</strong> is the only global role that grants anything on its own. Everyone else gets access from their business role,
                    which owners edit in Settings → Roles. The old global Users / Roles / Permissions screens were retired because any owner could change them.
                </div>
            </div>
            <div className="row g-4">
                {roles.data.map((r) => (
                    <div className="col-md-6 col-xl-4" key={r.id}>
                        <SectionCard icon="shield-lock" title={r.name} subtitle={`${r.users} user${r.users === 1 ? '' : 's'} · ${r.permissions} permission${r.permissions === 1 ? '' : 's'}`}>
                            {r.sample.length ? (
                                <div className="d-flex flex-wrap gap-1">
                                    {r.sample.map((p) => <span key={p} className="pill small">{p}</span>)}
                                    {r.permissions > r.sample.length && <span className="text-muted small">+{r.permissions - r.sample.length} more</span>}
                                </div>
                            ) : <span className="text-muted small">No permissions of its own.</span>}
                        </SectionCard>
                    </div>
                ))}
            </div>
            {roles.last_page > 1 && (
                <div className="d-flex gap-2 justify-content-end">
                    {roles.prev_page_url && <Link className="btn btn-soft btn-sm" href={roles.prev_page_url}>Previous</Link>}
                    {roles.next_page_url && <Link className="btn btn-soft btn-sm" href={roles.next_page_url}>Next</Link>}
                </div>
            )}
        </>
    );
}
