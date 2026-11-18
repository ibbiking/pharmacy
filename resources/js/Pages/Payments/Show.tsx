import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import KpiCard from '@/Components/KpiCard';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { BackLink, DrawerLink } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';

interface Props {
    direction: 'in' | 'out';
    payment: { id: number; number: string; date: string; amount: number; unapplied: number; method: string; reference: string | null; notes: string | null; by: string | null;
        party: { id: number; name: string; company: string | null } | null; allocations: { document_id: number; number: string | null; amount: number; balance: number | null; href?: string | null }[] };
    canDelete: boolean;
    currency: string;
}

export default function PaymentShow({ direction, payment: p, canDelete, currency }: Props) {
    const [confirm, setConfirm] = useState(false);
    const side = direction === 'in' ? 'selling' : 'buying';
    const money = (v: number) => formatMoney(v, currency);
    return (
        <>
            <Head title={`Payment ${p.number}`} />
            <BackLink href={route(`${side}.payments.index`)}>{direction === 'in' ? 'Payments received' : 'Bill payments'}</BackLink>
            <PageHeader icon="cash-coin" title={`Payment ${p.number}`} description={[p.party?.name, p.date, p.method, p.reference].filter(Boolean).join(' · ')}
                actions={canDelete && <button type="button" className="btn btn-outline-danger" onClick={() => setConfirm(true)}><i className="bi bi-trash me-1" />Delete</button>} />
            <div className="row g-3 mb-3">
                <div className="col-sm-6"><KpiCard icon="cash-stack" label="Amount" value={money(p.amount)} accent="var(--app-success)" /></div>
                <div className="col-sm-6"><KpiCard icon="piggy-bank" label="Kept as credit" value={money(p.unapplied)} accent="var(--app-warning)" /></div>
            </div>
            <SectionCard icon="receipt" title="Applied to" flush>
                {p.allocations.length === 0 ? <p className="text-muted p-3 mb-0">Not applied to any document.</p> : (
                    <table className="table dt-table align-middle mb-0"><thead><tr><th>Document</th><th className="text-end">Applied</th><th className="text-end">Still open</th></tr></thead>
                        <tbody>{p.allocations.map((a) => <tr key={a.document_id}><td><DrawerLink href={a.href ?? route(`${side}.docs.show`, a.document_id)} className="fw-semibold" title={a.number ?? ''}>{a.number}</DrawerLink></td><td className="text-end tabular">{money(a.amount)}</td><td className="text-end tabular">{money(a.balance ?? 0)}</td></tr>)}</tbody></table>
                )}
            </SectionCard>
            {p.notes && <p className="text-muted">{p.notes}</p>}
            {p.by && <p className="small text-muted">Recorded by {p.by}</p>}
            <ConfirmDialog open={confirm} danger title="Delete this payment?" confirmLabel="Delete" message="What it paid becomes open again, and its journal is reversed."
                onClose={() => setConfirm(false)} onConfirm={() => router.delete(route(`${side}.payments.destroy`, p.id))} />
        </>
    );
}
