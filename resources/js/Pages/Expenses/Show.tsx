import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import KpiCard from '@/Components/KpiCard';
import Modal from '@/Components/Modal';
import { BackLink, DrawerLink } from '@/Components/Drawer';
import { statusPill } from '@/Components/Documents/status';
import { formatMoney } from '@/lib/format';

interface Props {
    expense: { id: number; number: string; date: string; merchant: string | null; description: string | null; category: string | null; amount: number; tax: string | null; tax_amount: number; paid_by: string; payment_method: string;
        status: string; by: string | null; approver: string | null; approved_at: string | null; reimbursed_at: string | null; rejection_reason: string | null; receipt: string | null };
    can: Record<'edit' | 'submit' | 'approve' | 'reject' | 'reimburse' | 'archive', boolean>;
    currency: string;
}

export default function ExpenseShow({ expense: e, can, currency }: Props) {
    const [rejecting, setRejecting] = useState(false);
    const [reason, setReason] = useState('');
    const act = (action: string, data: Record<string, string> = {}) => router.post(route('expenses.action', [e.id, action]), data, { preserveScroll: true });
    return (
        <>
            <Head title={`Expense ${e.number}`} />
            <BackLink href={route('expenses.index')}>All expenses</BackLink>
            <PageHeader icon="wallet2" title={`Expense ${e.number}`} description={[e.merchant, e.category, e.date, e.by && `by ${e.by}`].filter(Boolean).join(' · ')} />
            <div className="d-flex flex-wrap gap-2 align-items-center mb-3">
                {statusPill(e.status)}<span className="flex-grow-1" />
                {can.edit && <DrawerLink href={route('expenses.edit', e.id)} className="btn btn-light" title={`Edit ${e.number}`}><i className="bi bi-pencil me-1" />Edit</DrawerLink>}
                {can.submit && <button type="button" className="btn btn-primary" onClick={() => act('submit')}><i className="bi bi-send me-1" />Submit for approval</button>}
                {can.approve && <button type="button" className="btn btn-success" onClick={() => act('approve')}><i className="bi bi-check2-circle me-1" />Approve</button>}
                {can.reject && <button type="button" className="btn btn-outline-danger" onClick={() => setRejecting(true)}><i className="bi bi-x-circle me-1" />Reject</button>}
                {can.reimburse && <button type="button" className="btn btn-primary" onClick={() => act('reimburse')}><i className="bi bi-cash-coin me-1" />Mark reimbursed</button>}
                {can.archive && <button type="button" className="btn btn-outline-danger" onClick={() => act('archive')}><i className="bi bi-archive me-1" />Archive</button>}
            </div>
            {e.rejection_reason && <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-danger)' }}><i className="bi bi-x-circle" /><div><strong>Rejected{e.approver && ` by ${e.approver}`}</strong><p>{e.rejection_reason}</p></div></div>}
            <div className="row g-3 mb-3">
                <div className="col-sm-4"><KpiCard icon="cash-stack" label="Amount" value={formatMoney(e.amount, currency)} accent="var(--chart-5)" /></div>
                <div className="col-sm-4"><KpiCard icon="percent" label={e.tax ? `Tax (${e.tax})` : 'Tax'} value={formatMoney(e.tax_amount, currency)} accent="var(--chart-1)" /></div>
                <div className="col-sm-4"><KpiCard icon={e.paid_by === 'employee' ? 'person' : 'building'} label="Paid by" value={e.paid_by === 'employee' ? 'Employee' : `Business (${e.payment_method})`} accent="var(--chart-6)" /></div>
            </div>
            <SectionCard icon="info-circle" title="Details">
                <dl className="row small mb-0">
                    <dt className="col-4 text-muted fw-normal">Description</dt><dd className="col-8">{e.description ?? '—'}</dd>
                    <dt className="col-4 text-muted fw-normal">Approved</dt><dd className="col-8">{e.approved_at && e.status !== 'rejected' ? `${e.approved_at} by ${e.approver}` : '—'}</dd>
                    <dt className="col-4 text-muted fw-normal">Reimbursed</dt><dd className="col-8">{e.reimbursed_at ?? '—'}</dd>
                    <dt className="col-4 text-muted fw-normal">Receipt</dt><dd className="col-8">{e.receipt ? <a href={e.receipt} target="_blank" rel="noreferrer"><i className="bi bi-paperclip me-1" />Open receipt</a> : 'None attached'}</dd>
                </dl>
            </SectionCard>
            <Modal open={rejecting} title="Reject this expense" icon="x-circle" onClose={() => setRejecting(false)}
                footer={<><button type="button" className="btn btn-light" onClick={() => setRejecting(false)}>Cancel</button><button type="button" className="btn btn-danger" disabled={!reason.trim()} onClick={() => { act('reject', { reason }); setRejecting(false); }}>Reject</button></>}>
                <label className="form-label" htmlFor="rj-reason">Why? (shown to the person who submitted it)</label>
                <textarea id="rj-reason" className="form-control" rows={3} maxLength={500} value={reason} onChange={(v) => setReason(v.target.value)} />
            </Modal>
        </>
    );
}
