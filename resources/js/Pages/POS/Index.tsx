import { Head, usePage } from '@inertiajs/react';
import axios, { type AxiosError } from 'axios';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Receipt, { type ReceiptLine, type ReceiptTax, type ReceiptTotals } from '@/Components/POS/Receipt';
import Modal from '@/Components/Modal';
import { toast } from '@/Components/Toaster';
import { formatMoney, formatNumber } from '@/lib/format';

interface Unit { id: number; name: string; base_quantity: number; price: number }
interface SearchHit {
    id: number; product_name: string; product_type: string | null; strength: { name: string } | null; farmula: string | null;
    price: number; default_category_id: number; categories: Unit[]; out_of_stock?: boolean; available_base_stock?: number;
}
type LotMode = 'fefo' | 'fifo';
/** What the cashier typed. Prices, totals, taxes and lots always come from the server quote. */
interface CartLine {
    key: string; product_id: number; name: string; type: string | null; units: Unit[]; category_id: number; qty: string;
    price: string; discount_type: 'percent' | 'amount'; discount: string; lot_mode: LotMode | null;
}
interface QuoteLine {
    line: number; price: number; system_price: number; overridden: boolean; line_base: number; discount: number; row_total: number; lot_mode: LotMode;
    included_tax: number | null; lots: { id: number; qty: number; batch: string | null; expiry: string | null }[];
    rules: { can_change_price: boolean; min_price: number | null; can_discount: boolean; max_discount_percent: number | null; max_discount_amount: number | null };
}
interface Issue { line: number | null; code: string; message: string; available?: number }
interface Quote {
    lines: QuoteLine[]; totals: ReceiptTotals & { additive_tax: number; cash_short: boolean }; taxes: ReceiptTax[];
    issues: Issue[]; can_checkout: boolean; invoice: { max_discount_percent: number | null; can_discount: boolean };
}
interface Props {
    receipt: { name: string; address: string | null; phone: string | null; note: string | null; next_invoice_no: string | null; fbr: boolean };
    defaultLotMode: LotMode;
    currency: string;
    cashier: string;
    cartKey: string;
}

let seq = Date.now();
const store = { get: (k: string) => { try { return localStorage.getItem(k); } catch { return null; } }, set: (k: string, v: string | null) => { try { if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch { /* private mode: cart just isn't kept */ } } };
const daysTo = (iso: string) => Math.round((new Date(`${iso}T00:00:00`).getTime() - new Date(new Date().toDateString()).getTime()) / 86400000);
const shortDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', { month: 'short', year: '2-digit' });

export default function POS({ receipt, defaultLotMode, currency, cashier, cartKey }: Props) {
    const { wording } = usePage().props;
    const [cart, setCart] = useState<CartLine[]>(() => { try { return JSON.parse(store.get(cartKey) ?? '[]') as CartLine[]; } catch { return []; } });
    const [invDiscount, setInvDiscount] = useState<{ type: 'amount' | 'percent'; value: string }>({ type: 'amount', value: '' });
    const [cash, setCash] = useState('');
    const [quote, setQuote] = useState<Quote | null>(null);
    const [quoteState, setQuoteState] = useState<'idle' | 'loading' | 'error'>('idle');
    const [q, setQ] = useState('');
    const [hits, setHits] = useState<SearchHit[]>([]);
    const [hitFor, setHitFor] = useState('');
    const [active, setActive] = useState(0);
    const [confirm, setConfirm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [nextNo, setNextNo] = useState(receipt.next_invoice_no);
    const [now, setNow] = useState(new Date());
    const searchRef = useRef<HTMLInputElement>(null);
    const cashRef = useRef<HTMLInputElement>(null);
    const quoteSeq = useRef(0);
    const searchSeq = useRef(0);
    const pendingSearch = useRef<Promise<SearchHit[]> | null>(null);

    // ---------------------------------------------------------------- cart persistence + stock hold
    useEffect(() => { store.set(cartKey, cart.length ? JSON.stringify(cart) : null); }, [cart, cartKey]);
    const holdPayload = useCallback((lines: CartLine[]) => JSON.stringify(lines.map((l) => ({
        product_id: l.product_id, category_id: l.category_id, quantity: Number(l.qty) || 0, base_stock_sale_price_id: null,
        unit_price: Number(l.price) || 0, price_group: (Number(l.price) || 0).toFixed(2), base_qty: 0,
    }))), []);
    useEffect(() => {
        // Keep this till's stock hold in step with the cart (other tills can't sell it meanwhile); refresh every 5 min.
        const sync = () => axios.post(route('pos.save-cart-session'), { cart: holdPayload(cart) }).catch(() => { /* advisory only: checkout re-checks stock */ });
        const t = window.setTimeout(sync, 700);
        const beat = cart.length ? window.setInterval(sync, 5 * 60 * 1000) : undefined;
        return () => { window.clearTimeout(t); if (beat) window.clearInterval(beat); };
    }, [cart, holdPayload]);
    useEffect(() => { const t = window.setInterval(() => setNow(new Date()), 30000); return () => window.clearInterval(t); }, []);

    // ---------------------------------------------------------------- server quote (the only source of numbers)
    const requestQuote = useCallback(() => {
        const id = ++quoteSeq.current;
        if (cart.length === 0) { setQuote(null); setQuoteState('idle'); return; }
        setQuoteState('loading');
        axios.post<Quote>(route('pos.quote'), {
            lines: cart.map((l) => ({
                product_id: l.product_id, category_id: l.category_id, qty: Number(l.qty) || 0, name: l.name,
                price: l.price.trim() === '' ? null : Number(l.price), lot_selection_mode: l.lot_mode,
                discount_type: l.discount_type, discount_percent: l.discount_type === 'percent' ? Number(l.discount) || 0 : 0,
                discount_amount: l.discount_type === 'amount' ? Number(l.discount) || 0 : 0,
            })),
            invoice_discount_type: invDiscount.type, invoice_discount_value: Number(invDiscount.value) || 0, cash_received: Number(cash) || 0,
        }).then(({ data }) => { if (id === quoteSeq.current) { setQuote(data); setQuoteState('idle'); } })
            .catch(() => { if (id === quoteSeq.current) setQuoteState('error'); });
    }, [cart, invDiscount, cash]);
    useEffect(() => { const t = window.setTimeout(requestQuote, 220); return () => window.clearTimeout(t); }, [requestQuote]);

    // ---------------------------------------------------------------- search + barcode
    const runSearch = useCallback((term: string): Promise<SearchHit[]> => {
        const id = ++searchSeq.current;
        const p = axios.get<SearchHit[]>(route('products.search'), { params: { q: term, current_cart: holdPayload(cart) } })
            .then(({ data }) => {
                const list = (Array.isArray(data) ? data : []).filter((h) => h && h.product_name);
                if (id === searchSeq.current) { setHits(list); setHitFor(term); setActive(0); }
                return list;
            }).catch(() => { if (id === searchSeq.current) toast('error', 'Search failed. Check the connection and try again.'); return []; });
        pendingSearch.current = p;
        return p;
    }, [cart, holdPayload]);
    useEffect(() => {
        const term = q.trim();
        if (term.length < 2) { setHits([]); setHitFor(''); return; }
        const t = window.setTimeout(() => runSearch(term), 180);
        return () => window.clearTimeout(t);
    }, [q]); // eslint-disable-line react-hooks/exhaustive-deps

    const add = (h: SearchHit) => {
        const unit = h.categories.find((c) => c.id === h.default_category_id) ?? h.categories[h.categories.length - 1];
        if (!unit) { toast('error', `${h.product_name} has no packaging set up yet.`); return; }
        if (h.out_of_stock) toast('info', `${h.product_name} is out of stock.`);
        setCart((c) => {
            const same = c.findIndex((l) => l.product_id === h.id && l.category_id === unit.id && l.price === '' && l.lot_mode === null);
            if (same >= 0) return c.map((l, i) => (i === same ? { ...l, qty: String((Number(l.qty) || 0) + 1) } : l));
            return [...c, { key: `l${++seq}`, product_id: h.id, name: h.product_name + (h.strength?.name ? ` ${h.strength.name}` : ''), type: h.product_type, units: h.categories,
                category_id: unit.id, qty: '1', price: '', discount_type: 'percent', discount: '', lot_mode: null }];
        });
        searchSeq.current++; // a search still in flight must not reopen the list after the item was added
        setQ(''); setHits([]); setHitFor('');
        searchRef.current?.focus();
    };
    const onSearchKey = async (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => (hits.length ? (a + 1) % hits.length : 0)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => (hits.length ? (a - 1 + hits.length) % hits.length : 0)); }
        else if (e.key === 'Escape') { setQ(''); setHits([]); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            const term = q.trim();
            if (!term) return;
            // A scanner types fast and presses Enter: wait for THIS term's result instead of adding the previous one.
            const list = hitFor === term ? hits : await runSearch(term);
            const pick = hitFor === term ? list[active] : list[0];
            if (pick) add(pick); else toast('info', `Nothing found for “${term}”.`);
        }
    };

    // ---------------------------------------------------------------- line edits
    const setLine = (key: string, patch: Partial<CartLine>) => setCart((c) => c.map((l) => (l.key === key ? { ...l, ...patch } : l)));
    const remove = (key: string) => setCart((c) => c.filter((l) => l.key !== key));
    const quoteFor = (i: number) => quote?.lines.find((x) => x.line === i);
    const issuesFor = (i: number) => quote?.issues.filter((x) => x.line === i) ?? [];
    const invoiceIssues = quote?.issues.filter((x) => x.line === null) ?? [];

    // ---------------------------------------------------------------- save & print
    const stale = quoteState !== 'idle' || !quote;
    const canSave = cart.length > 0 && !stale && !!quote?.can_checkout && !quote.totals.cash_short;
    const blocker = cart.length === 0 ? 'Add items first.' : quoteState === 'error' ? 'Prices couldn’t be refreshed.' : stale ? 'Updating prices…'
        : quote!.issues.length ? 'Fix the problems marked in red.' : quote!.totals.cash_short ? 'Cash received is less than the total.' : null;

    const save = () => {
        if (!canSave || !quote) return;
        setSaving(true);
        // Open the print window now (in the click), so the browser doesn't block it; it loads the SAVED invoice later.
        const win = window.open('', 'pos-receipt', 'width=420,height=680');
        axios.post(route('pos.save-invoice'), {
            cart: cart.map((l, i) => ({
                product_id: l.product_id, category_id: l.category_id, qty: Number(l.qty), name: l.name, price: quoteFor(i)?.price ?? 0,
                lot_selection_mode: l.lot_mode, discount_type: l.discount_type,
                discount_percent: l.discount_type === 'percent' ? Number(l.discount) || 0 : 0, discount_amount: l.discount_type === 'amount' ? Number(l.discount) || 0 : 0,
            })),
            invoice_discount_type: invDiscount.type, invoice_discount_value: Number(invDiscount.value) || 0, cash_received: Number(cash) || 0,
            grand_total: quote.totals.grand_total, subtotal: quote.totals.net,
        }).then(({ data }) => {
            if (win) win.location.href = route('pos.receipt', data.invoice_id);
            else toast('info', 'Pop-ups are blocked: allow them to print receipts. The sale is saved.');
            toast('success', `Invoice ${data.invoice_no} saved. Change: ${formatMoney(Math.max(0, (Number(cash) || 0) - Number(data.grand_total)), currency)}`);
            setCart([]); setCash(''); setInvDiscount({ type: 'amount', value: '' }); setConfirm(false);
            axios.get(route('pos.next-invoice-number')).then(({ data: n }) => setNextNo(n.invoice_no ?? null)).catch(() => setNextNo(null));
            searchRef.current?.focus();
        }).catch((e: AxiosError<{ error?: string; message?: string; available_category_qty?: number; product_name?: string }>) => {
            win?.close();
            const d = e.response?.data;
            const msg = d?.error === 'stock_exceeded' ? `Not enough stock for ${d.product_name ?? 'an item'} (left: ${d.available_category_qty ?? 0}). The cart was re-checked.`
                : d?.message ?? (e.response ? 'The sale could not be saved.' : 'No connection: the sale was not saved. Try again.');
            toast('error', msg);
            setConfirm(false);
            requestQuote();
        }).finally(() => setSaving(false));
    };

    // ---------------------------------------------------------------- keyboard: F2 search, F4 cash, F9 save
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'F2') { e.preventDefault(); searchRef.current?.focus(); }
            if (e.key === 'F4') { e.preventDefault(); cashRef.current?.focus(); cashRef.current?.select(); }
            if (e.key === 'F9') { e.preventDefault(); if (canSave) setConfirm(true); else if (blocker) toast('info', blocker); }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [canSave, blocker]);

    const receiptLines: ReceiptLine[] = useMemo(() => cart.map((l, i) => {
        const ql = quoteFor(i);
        const unit = l.units.find((u) => u.id === l.category_id);
        const d = Number(l.discount) || 0;
        return { key: l.key, name: l.name, type: l.type, unit: unit?.name, qty: Number(l.qty) || 0, price: ql?.price ?? 0, total: ql?.row_total ?? 0,
            discountLabel: ql && ql.discount > 0 ? (l.discount_type === 'percent' ? `${formatNumber(d, 2)}%` : formatNumber(d, 2)) : null, problem: issuesFor(i).length > 0 };
    }), [cart, quote]); // eslint-disable-line react-hooks/exhaustive-deps
    const invLabel = Number(invDiscount.value) > 0 ? (invDiscount.type === 'percent' ? `(${formatNumber(Number(invDiscount.value), 2)}%)` : `(${formatNumber(Number(invDiscount.value), 2)})`) : null;
    const money = (v: number) => formatMoney(v, currency);
    const quick = quote ? [quote.totals.grand_total, ...[500, 1000, 5000].filter((v) => v > quote.totals.grand_total)].slice(0, 4) : [];

    return (
        <>
            <Head title="POS" />
            <div className="pos-grid">
                <section className="pos-main">
                    <div className="pos-search">
                        <div className="input-icon-group">
                            <i className="bi bi-upc-scan lead-icon" />
                            <input ref={searchRef} autoFocus className="form-control form-control-lg" placeholder={`Scan a barcode or search ${(wording.products ?? 'products').toLowerCase()} by name or formula  (F2)`}
                                value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={onSearchKey} aria-label="Search products" role="combobox" aria-expanded={hits.length > 0} />
                            {q && <button type="button" className="trail-btn" aria-label="Clear search" onClick={() => { setQ(''); setHits([]); searchRef.current?.focus(); }}><i className="bi bi-x-lg" /></button>}
                        </div>
                        {hits.length > 0 && (
                            <ul className="pos-hits" role="listbox">
                                {hits.map((h, i) => (
                                    <li key={h.id} role="option" aria-selected={i === active} className={i === active ? 'active' : ''} onMouseEnter={() => setActive(i)} onMouseDown={(e) => { e.preventDefault(); add(h); }}>
                                        <span className={`dot ${h.out_of_stock ? 'out' : 'in'}`} />
                                        <span className="flex-grow-1 min-w-0">
                                            <strong className="d-block text-truncate">{h.product_name}{h.strength?.name && <span className="text-muted fw-normal"> {h.strength.name}</span>}</strong>
                                            <small className="text-muted d-block text-truncate">{[h.product_type, h.farmula].filter(Boolean).join(' · ')}</small>
                                        </span>
                                        <span className="text-end small">{h.out_of_stock ? <span className="text-danger">Out of stock</span> : <><strong className="tabular">{money(h.price)}</strong><span className="d-block text-muted">{h.categories.find((c) => c.id === h.default_category_id)?.name}</span></>}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="pos-cart">
                        {cart.length === 0 ? (
                            <div className="pos-empty"><i className="bi bi-cart3" /><strong>Cart is empty</strong><span>Scan a barcode or search above. Keys: F2 search · F4 cash · F9 save &amp; print</span></div>
                        ) : cart.map((l, i) => {
                            const ql = quoteFor(i);
                            const issues = issuesFor(i);
                            const unit = l.units.find((u) => u.id === l.category_id);
                            return (
                                <article key={l.key} className={`pos-line ${issues.length ? 'has-issue' : ''}`}>
                                    <div className="pos-line-head">
                                        <div className="min-w-0">
                                            <strong className="d-block text-truncate">{l.name}</strong>
                                            <div className="d-flex flex-wrap gap-1 mt-1">
                                                {ql?.lots.map((lot) => {
                                                    const days = lot.expiry ? daysTo(lot.expiry) : null;
                                                    return <span key={lot.id} className="expiry-chip" style={{ ['--accent' as string]: days === null ? 'var(--chart-8)' : days <= 30 ? 'var(--app-danger)' : days <= 90 ? 'var(--app-warning)' : 'var(--app-success)' }}
                                                        title={`${formatNumber(lot.qty, 2)} base units from this batch`}>{lot.batch ?? 'Batch'}{lot.expiry && ` · exp ${shortDate(lot.expiry)}`}</span>;
                                                })}
                                            </div>
                                        </div>
                                        <div className="text-end">
                                            <strong className="tabular fs-5">{ql ? money(ql.row_total) : '…'}</strong>
                                            {ql && ql.discount > 0 && <small className="d-block text-success">−{money(ql.discount)}</small>}
                                        </div>
                                        <button type="button" className="btn btn-icon btn-soft text-danger" aria-label={`Remove ${l.name}`} onClick={() => remove(l.key)}><i className="bi bi-trash" /></button>
                                    </div>
                                    <div className="pos-line-fields">
                                        <label>Qty<input type="number" min={0} step="any" inputMode="decimal" className="form-control form-control-sm" value={l.qty} onChange={(e) => setLine(l.key, { qty: e.target.value })} aria-label={`Quantity of ${l.name}`} /></label>
                                        <label>Unit<select className="form-select form-select-sm" value={l.category_id} onChange={(e) => setLine(l.key, { category_id: Number(e.target.value), price: '', qty: '1' })} aria-label={`Unit of ${l.name}`}>
                                            {l.units.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select></label>
                                        <label>Price{ql?.overridden && <span className="text-warning ms-1" title={`System price ${money(ql.system_price)}`}>changed</span>}
                                            <input type="number" min={0} step="0.01" className="form-control form-control-sm" placeholder={ql ? String(ql.system_price) : ''} value={l.price}
                                                disabled={!ql?.rules.can_change_price && l.price === ''} onChange={(e) => setLine(l.key, { price: e.target.value })}
                                                title={ql?.rules.can_change_price ? (ql.rules.min_price !== null ? `Not below ${money(ql.rules.min_price)}` : 'You can change the price') : 'Price changes are not allowed for this item'} aria-label={`Price of ${l.name}`} />
                                        </label>
                                        <label>Discount
                                            <div className="input-group input-group-sm">
                                                <input type="number" min={0} step="0.01" className="form-control" value={l.discount} disabled={ql ? !ql.rules.can_discount : false} onChange={(e) => setLine(l.key, { discount: e.target.value })} aria-label={`Discount on ${l.name}`}
                                                    placeholder={ql?.rules.max_discount_percent != null && l.discount_type === 'percent' ? `max ${ql.rules.max_discount_percent}` : ''} />
                                                <button type="button" className="btn btn-light" onClick={() => setLine(l.key, { discount_type: l.discount_type === 'percent' ? 'amount' : 'percent' })} aria-label="Switch discount type">{l.discount_type === 'percent' ? '%' : currency || 'Amt'}</button>
                                            </div>
                                        </label>
                                        <label>Batch order
                                            <div className="btn-group btn-group-sm w-100" role="radiogroup" aria-label={`Which batch sells first for ${l.name}`}>
                                                {(['fefo', 'fifo'] as LotMode[]).map((m) => {
                                                    const on = (l.lot_mode ?? defaultLotMode) === m;
                                                    return <button key={m} type="button" role="radio" aria-checked={on} className={`btn ${on ? 'btn-primary' : 'btn-light'}`} title={m === 'fefo' ? 'Earliest expiry first' : 'Oldest purchase first'}
                                                        onClick={() => setLine(l.key, { lot_mode: m === defaultLotMode ? null : m })}>{m.toUpperCase()}</button>;
                                                })}
                                            </div>
                                        </label>
                                    </div>
                                    {unit && unit.base_quantity > 1 && <small className="text-muted">1 {unit.name} = {formatNumber(unit.base_quantity, 0)} base units</small>}
                                    {issues.map((x, k) => (
                                        <div key={k} className="pos-issue"><i className="bi bi-exclamation-circle" /><span>{x.message}</span>
                                            {x.code === 'stock_exceeded' && x.available !== undefined && x.available > 0 && <button type="button" className="btn btn-sm btn-light ms-auto" onClick={() => setLine(l.key, { qty: String(x.available) })}>Use {formatNumber(x.available, 2)}</button>}
                                            {x.code === 'price_change_not_allowed' && <button type="button" className="btn btn-sm btn-light ms-auto" onClick={() => setLine(l.key, { price: '' })}>Use system price</button>}
                                            {x.code === 'discount_exceeded' && <button type="button" className="btn btn-sm btn-light ms-auto" onClick={() => setLine(l.key, { discount: '' })}>Remove discount</button>}
                                        </div>
                                    ))}
                                </article>
                            );
                        })}
                    </div>
                </section>

                <aside className="pos-side">
                    <div className="pos-receipt-wrap">
                        <Receipt business={receipt} invoiceNo={nextNo} fbr={receipt.fbr} date={now} lines={receiptLines} totals={quote?.totals ?? null} taxes={quote?.taxes ?? []} invoiceDiscountLabel={invLabel} />
                    </div>
                    <div className="pos-pay app-card">
                        <div className="row g-2 align-items-end">
                            <div className="col-6">
                                <label className="form-label small fw-semibold mb-1" htmlFor="pos-idisc">Invoice discount</label>
                                <div className="input-group input-group-sm">
                                    <input id="pos-idisc" type="number" min={0} step="0.01" className="form-control" value={invDiscount.value} disabled={quote ? !quote.invoice.can_discount : false}
                                        onChange={(e) => setInvDiscount((d) => ({ ...d, value: e.target.value }))} placeholder={quote?.invoice.max_discount_percent != null ? `max ${quote.invoice.max_discount_percent}%` : '0'} />
                                    <button type="button" className="btn btn-light" onClick={() => setInvDiscount((d) => ({ ...d, type: d.type === 'amount' ? 'percent' : 'amount' }))}>{invDiscount.type === 'percent' ? '%' : currency || 'Amt'}</button>
                                </div>
                            </div>
                            <div className="col-6">
                                <label className="form-label small fw-semibold mb-1" htmlFor="pos-cash">Cash received (F4)</label>
                                <input id="pos-cash" ref={cashRef} type="number" min={0} step="0.01" inputMode="decimal" className={`form-control form-control-sm ${quote?.totals.cash_short && cash ? 'is-invalid' : ''}`} value={cash} onChange={(e) => setCash(e.target.value)} />
                            </div>
                            {quick.length > 0 && <div className="col-12 d-flex flex-wrap gap-1">{quick.map((v, k) => <button key={k} type="button" className="btn btn-sm btn-light" onClick={() => setCash(String(v))}>{k === 0 ? 'Exact' : money(v)}</button>)}</div>}
                        </div>
                        {invoiceIssues.map((x, k) => <div key={k} className="pos-issue mt-2"><i className="bi bi-exclamation-circle" /><span>{x.message}</span></div>)}
                        {quoteState === 'error' && <div className="pos-issue mt-2"><i className="bi bi-wifi-off" /><span>Prices couldn’t be refreshed.</span><button type="button" className="btn btn-sm btn-light ms-auto" onClick={requestQuote}>Retry</button></div>}
                        <div className="d-flex align-items-center justify-content-between mt-3">
                            <div>
                                <div className="small text-muted">Total{quoteState === 'loading' && <span className="spinner-border spinner-border-sm ms-2" />}</div>
                                <div className="fs-3 fw-bold tabular">{quote ? money(quote.totals.grand_total) : money(0)}</div>
                                {quote && Number(cash) > 0 && <div className="small">Change: <strong className="tabular">{money(quote.totals.change)}</strong></div>}
                            </div>
                            <button type="button" className="btn btn-primary btn-lg" disabled={!canSave || saving} onClick={() => setConfirm(true)} title={blocker ?? 'Save and print (F9)'}>
                                <i className="bi bi-printer me-1" />Save &amp; print <small className="opacity-75">F9</small>
                            </button>
                        </div>
                        {blocker && cart.length > 0 && <small className="text-muted d-block mt-1">{blocker}</small>}
                        <small className="text-muted d-block mt-1">Cashier: {cashier}</small>
                    </div>
                </aside>
            </div>

            <Modal open={confirm} onClose={() => !saving && setConfirm(false)} icon="printer" width={420} title="Save and print?"
                footer={<>
                    <button type="button" className="btn btn-light" onClick={() => setConfirm(false)} disabled={saving}>Back</button>
                    <button type="button" className="btn btn-primary" onClick={save} disabled={saving || !canSave} autoFocus>{saving && <span className="spinner-border spinner-border-sm me-2" />}Save &amp; print</button>
                </>}>
                {quote && <dl className="row mb-0">
                    <dt className="col-6 fw-normal text-muted">Items</dt><dd className="col-6 text-end">{cart.length}</dd>
                    <dt className="col-6 fw-normal text-muted">Total</dt><dd className="col-6 text-end fw-bold tabular">{money(quote.totals.grand_total)}</dd>
                    <dt className="col-6 fw-normal text-muted">Cash</dt><dd className="col-6 text-end tabular">{money(quote.totals.cash_received)}</dd>
                    <dt className="col-6 fw-normal text-muted">Change</dt><dd className="col-6 text-end fw-bold tabular text-success fs-5">{money(quote.totals.change)}</dd>
                </dl>}
            </Modal>
        </>
    );
}
