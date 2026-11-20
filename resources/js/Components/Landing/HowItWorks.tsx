import { useEffect, useState } from 'react';

interface TypeLite { key: string; name: string; icon: string; accent: string }
interface Step { icon: string; title: string; text: string; points: string[] }

/** The same start for every business type. */
const START: Step[] = [
    { icon: 'person-plus', title: 'Sign up', text: 'Create your login in a minute: pick your kind of business, confirm your email and set a password.',
        points: ['No installation', 'One login can run several businesses'] },
    { icon: 'shop', title: 'Create your business, type is final', text: 'On the first sign-in the app asks for your business details and its type. The type is chosen once and never changes or mixes.',
        points: ['Need another type later? Create another business', 'Your screens use your type’s words'] },
    { icon: 'puzzle', title: 'Your modules are ready', text: 'Everything your type needs is switched on from the start. The type’s own parts are always on; the others can be switched off and on any time.',
        points: ['Warehouses, Books, Expense and credit are on from day one', 'Switching off never deletes data'] },
];

/** The same finish for every business type. */
const FINISH: Step = { icon: 'people', title: 'Run it with your team', text: 'Invite staff by email and give each a role: counter staff see only the till, an accountant sees the books.',
    points: ['Owner, Sales person or your own roles', 'Reports, dashboard and weekly summary email'] };

/** The middle of the story, per business type. A new type without its own story uses GENERIC. */
const STORY: Record<string, Step[]> = {
    pharmacy: [
        { icon: 'capsule', title: 'Add your medicines', text: 'Import from the ready-made generic medicine catalogue or add your own, with packaging (Box → Strip → Tablet) and prices.',
            points: ['Formula, strength and dosage form', 'Every medicine can expire by default'] },
        { icon: 'box-arrow-in-down', title: 'Buy stock in batches', text: 'Record a purchase with each batch and expiry date. Pay the supplier in full, in part or on credit.',
            points: ['Not paid in full? Its bill is kept for you', 'Pay the bill later, in parts if needed'] },
        { icon: 'cart-check', title: 'Sell at the counter', text: 'Scan or search; the till picks the batch that expires first and prints the receipt. Walk-in customers pay in full.',
            points: ['Saved customers can pay later (credit limit checked)', 'Expired stock is never sold'] },
        { icon: 'arrow-left-right', title: 'Returns and payments', text: 'A returned cash sale gives cash back; a returned credit sale becomes a credit note. Record what customers pay and what you pay suppliers.',
            points: ['Statements and ageing show who owes what', 'Expiry alerts tell you what to sell first'] },
        { icon: 'journal-text', title: 'Books keep themselves', text: 'Every sale, purchase, payment and expense posts to the accounts on its own.',
            points: ['Profit & loss, balance sheet, trial balance', 'Expenses with receipts and approval'] },
    ],
    inventory: [
        { icon: 'box-seam', title: 'Add your items', text: 'Add items with brand, variant and units of measure (Carton → Box → Piece) and their prices.',
            points: ['Items don’t expire unless you switch it on (e.g. food)', 'Barcodes for fast scanning'] },
        { icon: 'box-arrow-in-down', title: 'Buy stock', text: 'Record a purchase and the stock is ready to sell. Pay the supplier in full, in part or on credit.',
            points: ['Not paid in full? Its bill is kept for you', 'Pay the bill later, in parts if needed'] },
        { icon: 'cart-check', title: 'Sell at the counter', text: 'Scan, take the money, print the receipt. Walk-in customers pay in full; saved customers can pay later.',
            points: ['Credit limit checked before the sale', 'Returns of credit sales become credit notes'] },
        { icon: 'buildings', title: 'Stores and warehouses', text: 'Keep stock in several stores, move it between them and write off damaged goods. Choose which store the till sells from.',
            points: ['Stock by store and its value', 'Transfers and adjustments on record'] },
        { icon: 'journal-text', title: 'Payments and books', text: 'Record customer payments and supplier bills; every transaction posts to the accounts on its own.',
            points: ['Statements and ageing', 'Profit & loss, balance sheet, expenses'] },
    ],
    inventory_b2b: [
        { icon: 'person-vcard', title: 'Set up customers and items', text: 'Customer accounts with payment terms, credit limit and opening balance; items with units and prices.',
            points: ['No walk-in customers: every sale has an account', 'Vendors with their terms too'] },
        { icon: 'file-earmark-text', title: 'Quote → order → invoice', text: 'Send a quotation, turn it into a sales order, then an invoice. Issuing takes the stock and asks what was paid now: nothing, all or part.',
            points: ['Due dates from the customer’s terms', 'Credit notes from an invoice'] },
        { icon: 'truck', title: 'Ship it', text: 'Add a shipment to the order or invoice with carrier, tracking and packages; mark it shipped and delivered.',
            points: ['Printable delivery note', 'Status: packed → shipped → delivered'] },
        { icon: 'cart-plus', title: 'Buy on documents', text: 'Purchase order → goods received into stock → bill. Or enter a supplier bill directly. Pay now, in part or later.',
            points: ['Vendor credits from a bill', 'Short deliveries handled line by line'] },
        { icon: 'cash-coin', title: 'Get paid, pay, keep books', text: 'Record payments in as many instalments as needed; statements and ageing show what is overdue. Every document posts to the accounts.',
            points: ['Receivables and payables ageing', 'Profit & loss, balance sheet, expenses'] },
    ],
};
const GENERIC: Step[] = [
    { icon: 'box-seam', title: 'Add what you sell', text: 'Add your products with their units and prices.', points: ['Wording follows your business type'] },
    { icon: 'box-arrow-in-down', title: 'Buy stock', text: 'Record purchases; pay in full, in part or on credit.', points: ['Bills kept for what you owe'] },
    { icon: 'cart-check', title: 'Sell', text: 'Sell and print the receipt; saved customers can pay later.', points: ['Credit limit checked'] },
];

/** "How it works": the story from sign-up to running the business, one tab per business type, step by step. */
export default function HowItWorks({ types }: { types: TypeLite[] }) {
    const [typeKey, setTypeKey] = useState(types[0]?.key ?? 'pharmacy');
    const [step, setStep] = useState(0);
    const [playing, setPlaying] = useState(false);
    const type = types.find((t) => t.key === typeKey) ?? types[0];
    const steps = [...START, ...(STORY[typeKey] ?? GENERIC), FINISH];
    const s = steps[Math.min(step, steps.length - 1)]!;
    const last = steps.length - 1;

    useEffect(() => {
        if (!playing) return;
        const t = window.setTimeout(() => (step >= last ? setPlaying(false) : setStep(step + 1)), 4500);
        return () => window.clearTimeout(t);
    }, [playing, step, last]);

    const pickType = (k: string) => { setTypeKey(k); setStep(0); setPlaying(false); };
    const go = (i: number) => { setStep(Math.max(0, Math.min(last, i))); setPlaying(false); };
    const onKey = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowRight') { e.preventDefault(); go(step + 1); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); go(step - 1); }
    };

    return (
        <div className="hw" style={{ ['--accent' as string]: type?.accent ?? 'var(--app-primary)' }} onKeyDown={onKey}>
            <div className="hw-types" role="tablist" aria-label="Business type">
                <span className="hw-types-label">Show the story for</span>
                {types.map((t) => (
                    <button key={t.key} type="button" role="tab" aria-selected={t.key === typeKey} className={`hw-type ${t.key === typeKey ? 'active' : ''}`}
                        style={{ ['--accent' as string]: t.accent }} onClick={() => pickType(t.key)}>
                        <i className={`bi bi-${t.icon}`} />{t.name}
                    </button>
                ))}
            </div>

            <div className="hw-body">
                <ol className="hw-rail" aria-label="Steps">
                    {steps.map((x, i) => (
                        <li key={`${typeKey}-${x.title}`}>
                            <button type="button" className={`hw-dot ${i === step ? 'active' : i < step ? 'done' : ''}`} onClick={() => go(i)} aria-current={i === step ? 'step' : undefined}>
                                <span className="hw-num">{i < step ? <i className="bi bi-check-lg" /> : i + 1}</span>
                                <span className="hw-dot-title">{x.title}</span>
                            </button>
                        </li>
                    ))}
                </ol>

                <div className="hw-panel" aria-live="polite">
                    <div className="hw-progress"><span style={{ width: `${((step + 1) / steps.length) * 100}%` }} /></div>
                    <div key={`${typeKey}-${step}`} className="hw-card">
                        <div className="hw-card-head">
                            <span className="hw-icon"><i className={`bi bi-${s.icon}`} /></span>
                            <div>
                                <small>Step {step + 1} of {steps.length}{step < START.length ? ' · every business' : step === last ? ' · every business' : ` · ${type?.name ?? ''}`}</small>
                                <h3>{s.title}</h3>
                            </div>
                        </div>
                        <p>{s.text}</p>
                        <ul>{s.points.map((p) => <li key={p}><i className="bi bi-check2-circle" />{p}</li>)}</ul>
                    </div>
                    <div className="hw-controls">
                        <button type="button" className="btn btn-light" onClick={() => go(step - 1)} disabled={step === 0}><i className="bi bi-arrow-left me-1" />Back</button>
                        <button type="button" className="btn btn-light" onClick={() => { if (step >= last) setStep(0); setPlaying((p) => !p); }} aria-pressed={playing}>
                            <i className={`bi bi-${playing ? 'pause-fill' : 'play-fill'} me-1`} />{playing ? 'Pause' : 'Play the story'}
                        </button>
                        <button type="button" className="btn btn-primary hw-next" onClick={() => go(step + 1)} disabled={step === last}>Next<i className="bi bi-arrow-right ms-1" /></button>
                    </div>
                </div>
            </div>
        </div>
    );
}
