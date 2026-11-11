import { Head, router, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import AsyncSelect, { type Option } from '@/Components/AsyncSelect';
import { BackLink, CancelLink } from '@/Components/Drawer';
import { formatMoney } from '@/lib/format';

interface Line { key: number; account: Option | null; debit: string; credit: string; description: string }
let seq = 0;
const blank = (): Line => ({ key: ++seq, account: null, debit: '', credit: '', description: '' });

/** A balanced manual journal (debits = credits). */
export default function JournalForm({ today, currency }: { today: string; currency: string }) {
    const { errors } = usePage().props;
    const [date, setDate] = useState(today);
    const [memo, setMemo] = useState('');
    const [lines, setLines] = useState<Line[]>([blank(), blank()]);
    const [busy, setBusy] = useState(false);
    const dr = lines.reduce((t, l) => t + (Number(l.debit) || 0), 0);
    const cr = lines.reduce((t, l) => t + (Number(l.credit) || 0), 0);
    const balanced = dr > 0 && Math.abs(dr - cr) < 0.005;
    const set = (key: number, patch: Partial<Line>) => setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l)));
    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.post(route('accounting.journals.store'), { date, memo, lines: lines.map((l) => ({ account_id: l.account?.value ?? null, debit: l.debit || null, credit: l.credit || null, description: l.description || null })) },
            { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    };
    return (
        <>
            <Head title="Manual journal" />
            <BackLink href={route('accounting.journals.index')}>Journals</BackLink>
            <PageHeader icon="pencil-square" title="Manual journal" help="accounting" description="Each line is a debit or a credit. Debits must equal credits." />
            {errors.lines && <div className="alert alert-danger">{errors.lines}</div>}
            <form onSubmit={submit} noValidate>
                <SectionCard icon="info-circle" title="Journal" accent="var(--chart-8)">
                    <div className="row g-3">
                        <div className="col-md-3"><label className="form-label small fw-semibold" htmlFor="jf-date">Date</label><input id="jf-date" type="date" className="form-control" value={date} onChange={(e) => setDate(e.target.value)} /></div>
                        <div className="col-md-9"><label className="form-label small fw-semibold" htmlFor="jf-memo">Memo</label><input id="jf-memo" className={`form-control ${errors.memo ? 'is-invalid' : ''}`} maxLength={500} value={memo} onChange={(e) => setMemo(e.target.value)} placeholder="e.g. Opening bank balance" />{errors.memo && <div className="invalid-feedback">{errors.memo}</div>}</div>
                    </div>
                </SectionCard>
                <SectionCard icon="list-ul" title="Lines" accent="var(--chart-1)">
                    <div className="d-grid gap-2">{lines.map((l, i) => (
                        <div key={l.key} className="row g-2 align-items-end">
                            <div className="col-md-5"><label className="form-label small mb-1" htmlFor={`jl-a-${l.key}`}>Account</label><AsyncSelect entity="accounts" inputId={`jl-a-${l.key}`} value={l.account} onChange={(v) => set(l.key, { account: v })} invalid={!!errors[`lines.${i}.account_id`]} /></div>
                            <div className="col-6 col-md-2"><label className="form-label small mb-1" htmlFor={`jl-d-${l.key}`}>Debit</label><input id={`jl-d-${l.key}`} type="number" min="0" step="0.01" className={`form-control tabular ${errors[`lines.${i}.debit`] ? 'is-invalid' : ''}`} value={l.debit} onChange={(e) => set(l.key, { debit: e.target.value, credit: e.target.value ? '' : l.credit })} /></div>
                            <div className="col-6 col-md-2"><label className="form-label small mb-1" htmlFor={`jl-c-${l.key}`}>Credit</label><input id={`jl-c-${l.key}`} type="number" min="0" step="0.01" className="form-control tabular" value={l.credit} onChange={(e) => set(l.key, { credit: e.target.value, debit: e.target.value ? '' : l.debit })} /></div>
                            <div className="col-10 col-md-2"><label className="form-label small mb-1" htmlFor={`jl-n-${l.key}`}>Note</label><input id={`jl-n-${l.key}`} className="form-control" maxLength={255} value={l.description} onChange={(e) => set(l.key, { description: e.target.value })} /></div>
                            <div className="col-2 col-md-1">{lines.length > 2 && <button type="button" className="btn btn-icon btn-outline-danger" aria-label="Remove line" onClick={() => setLines((ls) => ls.filter((x) => x.key !== l.key))}><i className="bi bi-x-lg" /></button>}</div>
                            {(errors[`lines.${i}.account_id`] || errors[`lines.${i}.debit`]) && <div className="col-12 small text-danger">{errors[`lines.${i}.account_id`] ?? errors[`lines.${i}.debit`]}</div>}
                        </div>
                    ))}</div>
                    <button type="button" className="btn btn-soft btn-sm mt-2" onClick={() => setLines((ls) => [...ls, blank()])}><i className="bi bi-plus-lg me-1" />Add a line</button>
                    <div className="doc-totals mt-3">
                        <div><span>Debits</span><span className="tabular">{formatMoney(dr, currency)}</span></div>
                        <div><span>Credits</span><span className="tabular">{formatMoney(cr, currency)}</span></div>
                        <div className={`grand ${balanced ? 'text-success' : 'text-danger'}`}><span>{balanced ? 'Balanced' : 'Difference'}</span><span className="tabular">{formatMoney(Math.abs(dr - cr), currency)}</span></div>
                    </div>
                </SectionCard>
                <div className="d-flex justify-content-end gap-2"><CancelLink href={route('accounting.journals.index')} /><button type="submit" className="btn btn-primary" disabled={busy || !balanced}>{busy ? 'Posting…' : 'Post journal'}</button></div>
            </form>
        </>
    );
}
