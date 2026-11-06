import { Head, router } from '@inertiajs/react';
import type { Paginated, TableFilters } from '@/types';
import PageHeader from '@/Components/PageHeader';
import StatStrip from '@/Components/StatStrip';
import SectionCard from '@/Components/SectionCard';
import DataTable, { type Column } from '@/Components/DataTable';

type Kind = '' | 'owners' | 'staff' | 'none' | 'unverified';

interface Row {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    verified: boolean;
    super_admin: boolean;
    businesses: number;
    memberships: { name: string; role: string }[];
    created_at: string | null;
}

interface Props {
    users: Paginated<Row>;
    filters: TableFilters & { kind: Kind };
    stats: { count: number; owners: number; staff: number; none: number; unverified: number };
}

const initials = (n: string) => n.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]!.toUpperCase()).join('') || '?';

export default function Users({ users, filters, stats }: Props) {
    const filter = (kind: Kind) =>
        router.get(route('superadmin.users'), { ...filters, kind, page: 1 }, { only: ['users', 'filters'], preserveState: true, preserveScroll: true, replace: true });

    const columns: Column<Row>[] = [
        {
            key: 'name', label: 'Person', sortable: true,
            render: (u) => (
                <span className="d-flex align-items-center gap-2">
                    {u.avatar ? <img src={u.avatar} alt="" className="avatar-initials" style={{ objectFit: 'cover' }} /> : <span className="avatar-initials" style={{ width: 32, height: 32, fontSize: '0.75rem' }}>{initials(u.name)}</span>}
                    <span>
                        <span className="fw-semibold d-block">{u.name}{u.super_admin && <span className="pill ms-2" style={{ ['--accent' as string]: 'var(--app-danger)' }}>super admin</span>}</span>
                        <small className="text-muted">{u.email}{!u.verified && ' · not verified'}</small>
                    </span>
                </span>
            ),
        },
        {
            key: 'business_count', label: 'Businesses', sortable: true,
            render: (u) => u.businesses ? (
                <span className="small">
                    {u.memberships.map((m) => <span key={m.name} className="d-block">{m.name} <span className="text-muted">({m.role})</span></span>)}
                    {u.businesses > u.memberships.length && <span className="text-muted">+{u.businesses - u.memberships.length} more</span>}
                </span>
            ) : <span className="text-muted">None</span>,
        },
        { key: 'created_at', label: 'Joined', sortable: true, render: (u) => <span className="text-muted">{u.created_at}</span> },
    ];

    return (
        <>
            <Head title="Users" />
            <PageHeader icon="person-badge" title="Users" description="Everyone with a login on the platform. Read only: owners manage their people in Settings → Team." />
            <StatStrip
                stats={[
                    { key: 'all', icon: 'people', label: 'All users', value: stats.count, accent: 'var(--chart-1)', onClick: () => filter(''), selected: !filters.kind },
                    { key: 'owners', icon: 'person-check', label: 'Owners', value: stats.owners, accent: 'var(--chart-3)', onClick: () => filter('owners'), selected: filters.kind === 'owners' },
                    { key: 'staff', icon: 'person-workspace', label: 'Staff only', value: stats.staff, accent: 'var(--chart-2)', onClick: () => filter('staff'), selected: filters.kind === 'staff' },
                    { key: 'none', icon: 'person-dash', label: 'No business', value: stats.none, accent: 'var(--chart-8)', onClick: () => filter('none'), selected: filters.kind === 'none' },
                    { key: 'unverified', icon: 'envelope-exclamation', label: 'Email not verified', value: stats.unverified, accent: 'var(--chart-4)', onClick: () => filter('unverified'), selected: filters.kind === 'unverified' },
                ]}
            />
            <SectionCard icon="table" title="All users" subtitle="Search by name or email" flush>
                <DataTable page={users} columns={columns} filters={filters} only={['users', 'filters']} searchPlaceholder="Search name or email…" />
            </SectionCard>
        </>
    );
}
