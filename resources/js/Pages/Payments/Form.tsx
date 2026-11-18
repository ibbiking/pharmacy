import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import { BackLink, CancelLink } from '@/Components/Drawer';
import { toast } from '@/Components/Toaster';
import { formatMoney } from '@/lib/format';

interface OpenDoc { id: number; number: string; date: string; due_date: string | null; overdue: boolean; total: number; balance: number }
interface Props { direction: 'in' | 'out'; party: Option | null; preselect: { id: number; amount: number } | null; methods: { value: string; label: string }[]; today: string; currency: string }

/** Record money received / paid and apply it to open invoices / bills (oldest due first). */
export default function PaymentForm({ direction, party: preset, preselect, methods, today, currency }: Props) {
    const { errors } = usePage().props;
    const inbound = direction === 'in';
    const side = inbound ? 'selling' : 'buying';
    const [party, setParty] = useState<Option | null>(preset);
    const [docs, setDocs] = useState<OpenDoc[]>([]);
    const [nextPage, setNextPage] = useState<number | null>(null);
    const [apply, setApply] = useState<Record<number, string>>({});
    const [amount, setAmount] = useState(preselect ? String(preselect.amount) : '');
    const [date, setDate] = useState(today);
    const [method, setMethod] = useState(methods[0]?.value ?? 'cash');
    const [reference, setReference] = useState('');
    const [notes, setNotes] = useState('');
    const [busy, setBusy] = useState(false);
    const money = (v: number) => formatMoney(v, currency);

    const load = (page: number, reset: boolean) => {
        if (!party) return;
        axios.get(route(`${side}.payments.open`), { params: { party_id: party.value, page } }).then(({ data }) => {
            setDocs((d) => (reset ? data.data : [...d, ...data.data]));
            setNextPage(data.next_page);
            if (reset && preselect) setApply({ [preselect.id]: String(preselect.amount) });
        }).catch(() => toast('error', 'Couldn’t load the open documents.'));
    };
    useEffect(() => { setDocs([]); setApply({}); load(1, true); }, [party?.value]); // eslint-disable-line react-hooks/exhaustive-deps

    const applied = useMemo(() => Object.values(apply).reduce((t, v) => t + (Number(v) || 0), 0), [apply]);
    /** Spread the amount over the open documents, oldest due first. */
    const autoApply = () => {
        let left = Number(amount) || 0;
        const next: Record<number, string> = {};
        for (const d of docs) { if (left <= 0) break; const v = Math.min(left, d.balance); next[d.id] = String(Math.round(v * 100) / 100); left -= v; }
        setApply(next);
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.post(route(`${side}.payments.store`), {
            [inbound ? 'customer_id' : 'supplier_id']: party?.value ?? null, date, amount, method, reference: reference || null, notes: notes || null,
            allocations: Object.entries(apply).filter(([, v]) => Number(v) > 0).map(([id, v]) => ({ document_id: Number(id), amount: v })),
        }, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: () => toast('error', 'Check the payment: see the red messages.') });
    };
    const fb = (k: string) => errors[k] && <div className="invalid-feedback d-block">{errors[k]}</div>;

    return (
        <>
            <Head title={inbound ? 'Record payment' : 'Pay bills'} />
            <BackLink href={route(`${side}.payments.index`)}>{inbound ? 'Payments received' : 'Bill payments'}</BackLink>
            <PageHeader icon="cash-coin" title={inbound ? 'Record a payment received' : 'Pay bills'} help={inbound ? 'b2b-sales' : 'b2b-purchasing'}
                description="Apply it to open documents. Anything not applied is kept as credit." />
            <form onSubmit={submit} noValidate>
                <SectionCard icon="info-circle" title="Payment" accent="var(--app-success)">
                    <div className="row g-3">
                        <div className="col-md-6"><label className="form-label small fw-semibold" htmlFor="pay-party">{inbound ? 'Customer' : 'Supplier'}</label>
                            <AsyncSelect entity={inbound ? 'customers' : 'suppliers'} inputId="pay-party" value={party} onChange={setParty} invalid={!!(errors.customer_id || errors.supplier_id)} />{fb(inbound ? 'customer_id' : 'supplier_id')}</div>
                        <div className="col-6 col-md-3"><label className="form-label small fw-semibold" htmlFor="pay-amount">Amount</label>
                            <input id="pay-amount" type="number" min="0" step="0.01" className={`form-control tabular ${errors.amount ? 'is-invalid' : ''}`} value={amount} onChange={(e) => setAmount(e.target.value)} />{fb('amount')}</div>
                        <div className="col-6 col-md-3"><label className="form-label small fw-semibold" htmlFor="pay-date">Date</label>
                            <input id="pay-date" type="date" max={today} className="form-control" value={date} onChange={(e) => setDate(e.target.value)} />{fb('date')}</div>
                        <div className="col-6 col-md-3"><label className="form-label small fw-semibold" htmlFor="pay-method">Method</label>
                            <select id="pay-method" className="form-select" value={method} onChange={(e) => setMethod(e.target.value)}>{methods.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}</select></div>
                        <div className="col-6 col-md-3"><label className="form-label small fw-semibold" htmlFor="pay-ref">Reference <span className="text-muted fw-normal">Optional</span></label>
                            <input id="pay-ref" className="form-control" maxLength={100} value={reference} onChange={(e) => setReference(e.target.value)} placeholder="Cheque / transaction no" /></div>
                        <div className="col-md-6"><label className="form-label small fw-semibold" htmlFor="pay-notes">Notes <span className="text-muted fw-normal">Optional</span></label>
                            <input id="pay-notes" className="form-control" maxLength={500} value={notes} onChange={(e) => setNotes(e.target.value)} /></div>
                    </div>
                </SectionCard>
                <SectionCard icon="receipt" title={inbound ? 'Open invoices' : 'Open bills'} accent="var(--chart-4)" flush
                    actions={docs.length > 0 && <button type="button" className="btn btn-sm btn-soft" onClick={autoApply}><i className="bi bi-magic me-1" />Apply oldest first</button>}>
                    {!party ? <p className="text-muted p-3 mb-0">Pick the {inbound ? 'customer' : 'supplier'} first.</p> : docs.length === 0 ? <p className="text-muted p-3 mb-0">Nothing open: the whole amount is kept as credit.</p> : (
                        <div className="table-responsive"><table className="table dt-table align-middle mb-0">
                            <thead><tr><th>Number</th><th>Due</th><th className="text-end">Open</th><th className="text-end" style={{ width: 160 }}>Apply</th></tr></thead>
                            <tbody>{docs.map((d, i) => (
                                <tr key={d.id}>
                                    <td className="fw-semibold">{d.number}<small className="d-block text-muted">{d.date}</small></td>
                                    <td className={d.overdue ? 'text-danger fw-semibold' : 'text-muted'}>{d.due_date ?? '—'}</td>
                                    <td className="text-end tabular">{money(d.balance)}</td>
                                    <td className="text-end"><input type="number" min="0" max={d.balance} step="0.01" aria-label={`Apply to ${d.number}`} className={`form-control form-control-sm text-end tabular ${errors[`allocations.${i}.amount`] ? 'is-invalid' : ''}`}
                                        value={apply[d.id] ?? ''} onChange={(e) => setApply((a) => ({ ...a, [d.id]: e.target.value }))} /></td>
                                </tr>
                            ))}</tbody>
                        </table></div>
                    )}
                    {nextPage && <div className="p-2 text-center"><button type="button" className="btn btn-sm btn-light" onClick={() => load(nextPage, false)}>Show more</button></div>}
                    <div className="doc-totals p-3">
                        <div><span>Applied</span><span className="tabular">{money(applied)}</span></div>
                        <div className={applied > (Number(amount) || 0) + 0.004 ? 'text-danger' : ''}><span>Kept as credit</span><span className="tabular">{money(Math.max(0, (Number(amount) || 0) - applied))}</span></div>
                    </div>
                </SectionCard>
                <div className="save-bar d-flex justify-content-end gap-2 mt-3"><CancelLink href={route(`${side}.payments.index`)} /><button type="submit" className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Save payment'}</button></div>
            </form>
        </>
    );
}
