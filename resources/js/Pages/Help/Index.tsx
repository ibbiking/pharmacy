import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';

interface Topic { key: string; title: string; icon: string; accent: string; summary: string; steps: number }

export default function HelpIndex({ topics }: { topics: Topic[] }) {
    const [q, setQ] = useState('');
    const shown = topics.filter((t) => `${t.title} ${t.summary}`.toLowerCase().includes(q.trim().toLowerCase()));

    return (
        <>
            <Head title="Help Center" />
            <PageHeader icon="life-preserver" title="Help Center" description="Short, plain explanations of how things work, with links straight to the right screen." />
            <div className="quick-search mb-4" style={{ maxWidth: 520 }}>
                <i className="bi bi-search" />
                <input type="search" className="form-control" placeholder="Search help…" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Search help" />
            </div>
            <div className="row g-3">
                {shown.map((t) => (
                    <div className="col-md-6 col-xl-4" key={t.key}>
                        <Link href={route('help.show', t.key)} className="choice-card text-decoration-none" style={{ ['--accent' as string]: t.accent }}>
                            <span className="c-icon"><i className={`bi bi-${t.icon}`} /></span>
                            <strong>{t.title}</strong>
                            <p>{t.summary}</p>
                            <small className="text-muted">{t.steps} steps <i className="bi bi-arrow-right ms-1" /></small>
                        </Link>
                    </div>
                ))}
            </div>
            {shown.length === 0 && <EmptyState icon="search" title="No help topic matches" hint="Try another word." />}
        </>
    );
}
