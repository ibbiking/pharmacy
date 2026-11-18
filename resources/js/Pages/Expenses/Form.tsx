import { Head, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import { BackLink, CancelLink } from '@/Components/Drawer';
import { toast } from '@/Components/Toaster';
import { formatMoney } from '@/lib/format';

interface Props {
    expense: null | { id: number; number: string; date: string; merchant: string | null; description: string | null; amount: string; paid_by: 'business' | 'employee'; payment_method: string; category: Option | null; tax: Option | null; receipt: string | null; rejection_reason: string | null };
    today: string;
    currency: string;
}

export default function ExpenseForm({ expense: e, today, currency }: Props) {
    const { errors } = usePage().props;
    const [date, setDate] = useState(e?.date ?? today);
    const [category, setCategory] = useState<Option | null>(e?.category ?? null);
    const [merchant, setMerchant] = useState(e?.merchant ?? '');
    const [description, setDescription] = useState(e?.description ?? '');
    const [amount, setAmount] = useState(e?.amount ?? '');
    const [tax, setTax] = useState<Option | null>(e?.tax ?? null);
    const [paidBy, setPaidBy] = useState<'business' | 'employee'>(e?.paid_by ?? 'business');
    const [method, setMethod] = useState(e?.payment_method ?? 'cash');
    const [receipt, setReceipt] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);
    const rate = Number(tax?.meta?.rate ?? 0);
    const taxPart = rate ? (Number(amount) || 0) * rate / (100 + rate) : 0;

    const send = (submit: boolean) => (ev?: FormEvent) => {
        ev?.preventDefault();
        const data = { date, expense_category_id: category?.value ?? '', merchant, description, amount, tax_id: tax?.value ?? '', paid_by: paidBy, payment_method: method, submit: submit ? 1 : 0, ...(receipt ? { receipt } : {}) };
        router.post(e ? route('expenses.update', e.id) : route('expenses.store'), data, { forceFormData: true, preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false), onError: () => toast('error', 'Check the expense: see the red messages.') });
    };
    const fb = (k: string) => errors[k] && <div className="invalid-feedback d-block">{errors[k]}</div>;

    return (
        <>
            <Head title={e ? `Expense ${e.number}` : 'New expense'} />
            <BackLink href={route('expenses.index')}>All expenses</BackLink>
            <PageHeader icon="wallet2" title={e ? `Edit ${e.number}` : 'New expense'} help="expense" description="Save as a draft, or submit it for approval straight away." />
            {e?.rejection_reason && <div className="callout mb-3" style={{ ['--accent' as string]: 'var(--app-danger)' }}><i className="bi bi-x-circle" /><div><strong>Rejected</strong><p>{e.rejection_reason}</p></div></div>}
            {errors.expense && <div className="alert alert-danger">{errors.expense}</div>}
            <form onSubmit={send(true)} noValidate>
                <SectionCard icon="receipt" title="Expense" accent="var(--chart-5)">
                    <div className="row g-3">
                        <div className="col-md-4"><label className="form-label small fw-semibold" htmlFor="ex-date">Date</label><input id="ex-date" type="date" max={today} className={`form-control ${errors.date ? 'is-invalid' : ''}`} value={date} onChange={(v) => setDate(v.target.value)} />{fb('date')}</div>
                        <div className="col-md-8"><label className="form-label small fw-semibold" htmlFor="ex-cat">Category</label><AsyncSelect entity="expense-categories" inputId="ex-cat" value={category} onChange={setCategory} invalid={!!errors.expense_category_id} />{fb('expense_category_id')}</div>
                        <div className="col-md-6"><label className="form-label small fw-semibold" htmlFor="ex-merchant">Merchant <span className="text-muted fw-normal">Optional</span></label><input id="ex-merchant" className="form-control" maxLength={150} value={merchant} onChange={(v) => setMerchant(v.target.value)} placeholder="Where it was spent" /></div>
                        <div className="col-6 col-md-3"><label className="form-label small fw-semibold" htmlFor="ex-amount">Amount (incl. tax)</label><input id="ex-amount" type="number" min="0" step="0.01" className={`form-control tabular ${errors.amount ? 'is-invalid' : ''}`} value={amount} onChange={(v) => setAmount(v.target.value)} />{fb('amount')}</div>
                        <div className="col-6 col-md-3"><label className="form-label small fw-semibold" htmlFor="ex-tax">Tax in it</label><AsyncSelect entity="taxes" inputId="ex-tax" value={tax} onChange={setTax} placeholder="None" />{taxPart > 0 && <small className="text-muted">{formatMoney(taxPart, currency)} recoverable</small>}</div>
                        <div className="col-12"><label className="form-label small fw-semibold" htmlFor="ex-desc">Description <span className="text-muted fw-normal">Optional</span></label><textarea id="ex-desc" rows={2} className="form-control" maxLength={500} value={description} onChange={(v) => setDescription(v.target.value)} /></div>
                        <div className="col-md-6">
                            <span className="form-label small fw-semibold d-block">Who paid?</span>
                            <div className="btn-group w-100" role="group" aria-label="Who paid">
                                <button type="button" className={`btn ${paidBy === 'business' ? 'btn-primary' : 'btn-light'}`} onClick={() => setPaidBy('business')}><i className="bi bi-building me-1" />The business</button>
                                <button type="button" className={`btn ${paidBy === 'employee' ? 'btn-primary' : 'btn-light'}`} onClick={() => setPaidBy('employee')}><i className="bi bi-person me-1" />Me (reimburse)</button>
                            </div>
                        </div>
                        {paidBy === 'business' && <div className="col-md-6"><label className="form-label small fw-semibold" htmlFor="ex-method">Paid with</label><select id="ex-method" className="form-select" value={method} onChange={(v) => setMethod(v.target.value)}><option value="cash">Cash</option><option value="bank">Bank transfer</option><option value="card">Card</option></select></div>}
                        <div className="col-12"><label className="form-label small fw-semibold" htmlFor="ex-receipt">Receipt <span className="text-muted fw-normal">Optional · photo or PDF, up to 5 MB</span></label>
                            <input id="ex-receipt" type="file" accept="image/*,application/pdf" className={`form-control ${errors.receipt ? 'is-invalid' : ''}`} onChange={(v) => setReceipt(v.target.files?.[0] ?? null)} />{fb('receipt')}
                            {e?.receipt && !receipt && <small><a href={e.receipt} target="_blank" rel="noreferrer">Current receipt</a></small>}</div>
                    </div>
                </SectionCard>
                <div className="save-bar d-flex justify-content-end gap-2 mt-3">
                    <CancelLink href={e ? route('expenses.show', e.id) : route('expenses.index')} />
                    <button type="button" className="btn btn-light" disabled={busy} onClick={() => send(false)()}>Save draft</button>
                    <button type="submit" className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Submit for approval'}</button>
                </div>
            </form>
        </>
    );
}
