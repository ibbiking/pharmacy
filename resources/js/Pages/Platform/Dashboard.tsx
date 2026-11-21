import { Head, Link } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import KpiCard from '@/Components/KpiCard';
import SectionCard from '@/Components/SectionCard';

interface Props {
    stats: { businesses: number; new_businesses: number; people: number; sales_today: number; sales_30: number };
    types: { key: string; name: string; count: number }[];
    recent: { id: number; name: string; type: string; created_at: string | null }[];
    activity: { action: string; admin: string | null; business: string | null; at: string }[];
}

const TYPE_ICON: Record<string, string> = { pharmacy: 'capsule', inventory: 'shop', inventory_b2b: 'truck' };
const ACTION: Record<string, string> = {
    login: 'Signed in', logout: 'Signed out', 'business.open': 'Opened business', 'business.leave': 'Left business',
    'module.on': 'Switched a module on', 'module.off': 'Switched a module off', block: 'Blocked', unblock: 'Unblocked',
    'admin.add': 'Added a platform admin', 'admin.on': 'Switched an admin on', 'admin.off': 'Switched an admin off', 'admin.archive': 'Archived an admin',
};

export default function PlatformDashboard({ stats, types, recent, activity }: Props) {
    return (
        <>
            <Head title="Platform" />
            <PageHeader icon="speedometer2" title="Platform dashboard" description="Every business of every type, at a glance." />
            <div className="row g-3 mb-3">
                <div className="col-6 col-lg-3"><KpiCard icon="buildings" label="Businesses" value={stats.businesses} accent="var(--chart-1)" /></div>
                <div className="col-6 col-lg-3"><KpiCard icon="stars" label="New (30 days)" value={stats.new_businesses} accent="var(--chart-2)" /></div>
                <div className="col-6 col-lg-3"><KpiCard icon="people" label="People" value={stats.people} accent="var(--chart-4)" /></div>
                <div className="col-6 col-lg-3"><KpiCard icon="receipt" label="Sales today / 30 days" value={`${stats.sales_today} / ${stats.sales_30}`} accent="var(--chart-5)" /></div>
            </div>
            <div className="row g-3">
                <div className="col-lg-4">
                    <SectionCard icon="grid" title="Business types">
                        <div className="list-group list-group-flush">
                            {types.map((t) => (
                                <Link key={t.key} href={route('platform.businesses', { type: t.key })} className="list-group-item list-group-item-action d-flex align-items-center gap-2">
                                    <i className={`bi bi-${TYPE_ICON[t.key] ?? 'building'}`} /><span>{t.name}</span><span className="ms-auto badge text-bg-light">{t.count}</span>
                                </Link>
                            ))}
                        </div>
                    </SectionCard>
                </div>
                <div className="col-lg-4">
                    <SectionCard icon="clock-history" title="Newest businesses">
                        <div className="list-group list-group-flush">
                            {recent.map((b) => (
                                <Link key={b.id} href={route('platform.businesses.show', b.id)} className="list-group-item list-group-item-action">
                                    <strong className="d-block">{b.name}</strong><small className="text-muted">{b.type} · {b.created_at}</small>
                                </Link>
                            ))}
                            {recent.length === 0 && <p className="text-muted text-center py-3 mb-0">No businesses yet.</p>}
                        </div>
                    </SectionCard>
                </div>
                <div className="col-lg-4">
                    <SectionCard icon="journal-text" title="Platform activity">
                        <ul className="list-unstyled mb-0 small">
                            {activity.map((a, i) => (
                                <li key={i} className="py-1 border-bottom">
                                    <strong>{a.admin ?? '—'}</strong> {ACTION[a.action] ?? a.action}{a.business ? <> · <span className="text-muted">{a.business}</span></> : null}
                                    <div className="text-muted">{a.at}</div>
                                </li>
                            ))}
                            {activity.length === 0 && <li className="text-muted text-center py-3">Nothing yet.</li>}
                        </ul>
                    </SectionCard>
                </div>
            </div>
        </>
    );
}
