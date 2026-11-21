import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';
import ConfirmDialog from '@/Components/ConfirmDialog';

interface Row {
    id: number;
    name: string;
    type: string | null;
    owner: { name: string; email: string } | null;
    members: number;
    last_sale_at: string | null;
    created_at: string | null;
}

interface Props {
    businesses: Paginated<Row>;
    filters: TableFilters & { status: '' | 'active' | 'idle' | 'new'; type: string };
    stats: { count: number; active: number; idle: number; new: number };
}

export default function Businesses({ businesses, filters, stats }: Props) {
    const [opening, setOpening] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);
    const filter = (status: Props['filters']['status']) =>
        router.get(route('platform.businesses'), { ...filters, status, page: 1 }, { only: ['businesses', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const byType = (type: string) =>
        router.get(route('platform.businesses'), { ...filters, type, page: 1 }, { only: ['businesses', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const open = () => {
        if (!opening) return;
        router.post(route('platform.businesses.open', opening.id), {}, { onStart: () => setBusy(true), onFinish: () => { setBusy(false); setOpening(null); } });
    };

    const columns: Column<Row>[] = [
        {
            key: 'name', label: 'Business', sortable: true,
            render: (b) => (
                <span className="d-flex align-items-center gap-2">
                    <span className="avatar-tile" style={{ ['--accent' as string]: `var(--chart-${(b.id % 8) + 1})` }}><i className="bi bi-shop" /></span>
                    <span>
                        <Link href={route('platform.businesses.show', b.id)} className="fw-semibold d-block text-reset">{b.name}</Link>
                        <small className="text-muted text-capitalize">{b.type ?? '—'}</small>
                    </span>
                </span>
            ),
        },
        { key: 'owner', label: 'Owner', render: (b) => (b.owner ? <span><span className="d-block">{b.owner.name}</span><small className="text-muted">{b.owner.email}</small></span> : <span className="text-muted">No owner</span>) },
        { key: 'members', label: 'People', sortable: true, align: 'end', className: 'tabular' },
        { key: 'last_sale_at', label: 'Last sale', sortable: true, render: (b) => b.last_sale_at ?? <span className="text-muted">Never</span> },
        { key: 'created_at', label: 'Joined', sortable: true, render: (b) => <span className="text-muted">{b.created_at}</span> },
        {
            key: 'actions', label: '', align: 'end',
            render: (b) => (
                <span className="d-inline-flex gap-1">
                    <Link href={route('platform.businesses.show', b.id)} className="btn btn-sm btn-light" title="Modules, blocks and people"><i className="bi bi-puzzle me-1" />Manage</Link>
                    <button type="button" className="btn btn-sm btn-soft" onClick={() => setOpening(b)} title={`Work inside ${b.name}`}>
                        <i className="bi bi-box-arrow-in-right me-1" />Open
                    </button>
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="Businesses" />
            <PageHeader icon="buildings" title="Businesses" description="Every business on the platform. Open one to see and work in it exactly as its owner does." />
            <StatStrip
                stats={[
                    { key: 'all', icon: 'buildings', label: 'All businesses', value: stats.count, accent: 'var(--chart-1)', onClick: () => filter(''), selected: !filters.status },
                    { key: 'active', icon: 'activity', label: 'Sold in last 30 days', value: stats.active, accent: 'var(--chart-3)', onClick: () => filter('active'), selected: filters.status === 'active' },
                    { key: 'idle', icon: 'moon', label: 'No sale in 30 days', value: stats.idle, accent: 'var(--chart-8)', onClick: () => filter('idle'), selected: filters.status === 'idle' },
                    { key: 'new', icon: 'stars', label: 'Joined in 30 days', value: stats.new, accent: 'var(--chart-4)', onClick: () => filter('new'), selected: filters.status === 'new' },
                ]}
            />
            <div className="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Business type">
                {[['', 'All types'], ['pharmacy', 'Pharmacy'], ['inventory', 'Retail & Inventory'], ['inventory_b2b', 'Wholesale & Distribution']].map(([key, label]) => (
                    <button key={key} type="button" className={`btn btn-sm ${filters.type === key ? 'btn-primary' : 'btn-light'}`} onClick={() => byType(key)}>{label}</button>
                ))}
            </div>
            <SectionCard icon="table" title="All businesses" subtitle="Search by business name or a member's email" flush>
                <DataTable page={businesses} columns={columns} filters={filters} only={['businesses', 'filters']} searchPlaceholder="Search businesses or emails…" />
            </SectionCard>
            <ConfirmDialog
                open={opening !== null}
                title="Open this business?"
                message={opening ? `You will work inside “${opening.name}” with owner access. Everything you change is real. Use “Back to platform” at the top to leave.` : ''}
                confirmLabel="Open business"
                busy={busy}
                onConfirm={open}
                onClose={() => setOpening(null)}
            />
        </>
    );
}
