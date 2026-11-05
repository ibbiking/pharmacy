import { Head, Link, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import KpiCard from '@/Components/KpiCard';
import EmptyState from '@/Components/EmptyState';
import { formatMoney } from '@/lib/format';

type Group = 'active' | 'trialing' | 'past_due' | 'ended' | 'pending';
interface Row { id: number; business: string; business_type: string | null; plan: string; modules: number; status: string; interval: string; amount: number; currency: string; renews: string | null; cancel_at_period_end: boolean; created: string | null }
interface Props {
    subscriptions: Paginated<Row>;
    filters: TableFilters & { status?: Group | null };
    provider: { configured: boolean; mode: 'test' | 'live' | null; webhooks: boolean; free: boolean };
    stats: { mrr: number; counts: Record<Group, number>; total: number; revenue_30d: number; failed_events: number; last_event: string | null };
}

const ACCENT: Record<string, string> = { active: 'var(--app-success)', trialing: 'var(--app-info)', past_due: 'var(--app-warning)', unpaid: 'var(--app-danger)', incomplete: 'var(--app-warning)', canceled: 'var(--chart-8)', incomplete_expired: 'var(--chart-8)' };

export default function Subscriptions({ subscriptions, filters, provider, stats }: Props) {
    const filter = (status: Group | null) =>
        router.get(route('superadmin.billing.subscriptions'), { ...filters, status, page: 1 }, { only: ['subscriptions', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const columns: Column<Row>[] = [
        { key: 'business', label: 'Business', render: (r) => <div><strong>{r.business}</strong><div className="small text-muted text-capitalize">{r.business_type?.replace('_', ' ')}</div></div> },
        { key: 'plan', label: 'Plan', render: (r) => <div>{r.plan}<div className="small text-muted">{r.modules} module{r.modules === 1 ? '' : 's'} · {r.interval}</div></div> },
        { key: 'status', label: 'Status', render: (r) => <span className="pill" style={{ ['--accent' as string]: ACCENT[r.status] ?? 'var(--chart-8)' }}>{r.status.replace('_', ' ')}{r.cancel_at_period_end && ' · ends'}</span> },
        { key: 'amount', label: 'Amount', align: 'end', className: 'tabular', render: (r) => (r.amount > 0 ? formatMoney(r.amount, r.currency) : 'Free') },
        { key: 'renews', label: 'Renews / ends', render: (r) => r.renews ?? '—' },
        { key: 'created', label: 'Started', render: (r) => r.created ?? '—' },
    ];

    return (
        <>
            <Head title="Subscriptions" />
            <PageHeader icon="credit-card-2-front" title="Subscriptions" description="Every business's plan, recurring revenue and the health of the Stripe connection."
                actions={<Link href={route('superadmin.billing')} className="btn btn-light"><i className="bi bi-tags me-1" />Plans & pricing</Link>} />

            <div className="row g-3 mb-3">
                <div className="col-sm-6 col-xl-3"><KpiCard icon="graph-up-arrow" label="Monthly recurring revenue" value={formatMoney(stats.mrr)} accent="var(--chart-1)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon="cash-coin" label="Collected (30 days)" value={formatMoney(stats.revenue_30d)} accent="var(--chart-3)" /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon={provider.configured ? 'plug-fill' : 'plug'} label="Stripe" accent={provider.configured ? (provider.mode === 'live' ? 'var(--app-success)' : 'var(--app-info)') : 'var(--app-warning)'}
                    value={provider.configured ? (provider.mode === 'live' ? 'Live' : 'Test mode') : 'Not connected'} /></div>
                <div className="col-sm-6 col-xl-3"><KpiCard icon={stats.failed_events ? 'exclamation-triangle' : 'broadcast'} label={`Webhooks${stats.last_event ? ` · last ${stats.last_event}` : ''}`} accent={!provider.webhooks || stats.failed_events ? 'var(--app-warning)' : 'var(--app-success)'}
                    value={!provider.webhooks ? 'No secret set' : stats.failed_events ? `${stats.failed_events} failed` : 'Healthy'} /></div>
            </div>

            {!provider.configured && (
                <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-warning)' }}>
                    <i className="bi bi-key" />
                    <div><strong>Connect Stripe</strong><p>Add <code>STRIPE_KEY</code>, <code>STRIPE_SECRET</code> and <code>STRIPE_WEBHOOK_SECRET</code> to <code>.env</code> (test keys start with <code>pk_test_</code> / <code>sk_test_</code>), then run <code>php artisan config:clear</code>. For webhooks in development: <code>stripe listen --forward-to http://127.0.0.1:8080/api/stripe/webhook</code>. See INSTALL_NOTES.txt.</p></div>
                </div>
            )}

            <StatStrip stats={[
                { key: 'all', icon: 'collection', label: 'All', value: stats.total, accent: 'var(--chart-1)', onClick: () => filter(null), selected: !filters.status },
                { key: 'active', icon: 'check2-circle', label: 'Active', value: stats.counts.active, accent: 'var(--app-success)', onClick: () => filter('active'), selected: filters.status === 'active' },
                { key: 'trialing', icon: 'hourglass-split', label: 'On trial', value: stats.counts.trialing, accent: 'var(--app-info)', onClick: () => filter('trialing'), selected: filters.status === 'trialing' },
                { key: 'past_due', icon: 'exclamation-circle', label: 'Payment issues', value: stats.counts.past_due, accent: 'var(--app-warning)', onClick: () => filter('past_due'), selected: filters.status === 'past_due' },
                { key: 'pending', icon: 'clock', label: 'Checkout open', value: stats.counts.pending, accent: 'var(--chart-4)', onClick: () => filter('pending'), selected: filters.status === 'pending' },
                { key: 'ended', icon: 'x-circle', label: 'Ended', value: stats.counts.ended, accent: 'var(--chart-8)', onClick: () => filter('ended'), selected: filters.status === 'ended' },
            ]} />

            <SectionCard icon="credit-card-2-front" title="Subscriptions" flush>
                <DataTable page={subscriptions} columns={columns} filters={filters} only={['subscriptions', 'filters']} searchPlaceholder="Search businesses…"
                    empty={<EmptyState icon="credit-card" title="No subscriptions yet" hint={provider.free ? 'Launch pricing is on: businesses use every module for free.' : 'Subscriptions appear here when businesses choose a plan.'} />} />
            </SectionCard>
        </>
    );
}
