import { Head, Link } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import Toaster from '@/Components/Toaster';
import LandingHeader from '@/Components/Landing/LandingHeader';
import type { BusinessType } from '@/Components/BusinessTypePicker';

interface GuideModule {
    key: string; name: string; description: string; icon: string; accent: string; available: boolean; core: boolean;
    needs: string[]; starts_in: string[]; allowed_in: string[]; locked_in: string[];
}
type Words = Record<string, string | null>;
interface Props { brand: string; types: (BusinessType & { key: string; bundle: string[] })[]; modules: GuideModule[]; wording: Record<string, Words>; free: boolean; signedIn: boolean }

const SECTIONS = [
    ['types', 'Three kinds of business', 'shop'],
    ['one-type', 'One business, one type', 'lock'],
    ['tour', 'Demo tour per type', 'signpost-split'],
    ['words', 'Same thing, different names', 'translate'],
    ['modules', 'Modules', 'puzzle'],
    ['selling', 'Selling & credit', 'cart-check'],
    ['buying', 'Buying from suppliers', 'truck'],
    ['stock', 'Stock, batches & expiry', 'calendar2-check'],
    ['packaging', 'Packaging & prices', 'boxes'],
    ['team', 'Team & roles', 'people'],
    ['faq', 'Quick answers', 'question-circle'],
] as const;

/** Plain-language guide for owners and staff. Facts come from the app's own config (GuideController). */
export default function Guide({ brand, types, modules, wording, free, signedIn }: Props) {
    const [active, setActive] = useState<string>('types');
    const [tourType, setTourType] = useState<string>(types[0]?.key ?? 'pharmacy');
    useEffect(() => {
        const obs = new IntersectionObserver((entries) => entries.forEach((e) => e.isIntersecting && setActive(e.target.id)), { rootMargin: '-30% 0px -60% 0px' });
        SECTIONS.forEach(([id]) => { const el = document.getElementById(id); if (el) obs.observe(el); });
        return () => obs.disconnect();
    }, []);
    const typeName = (k: string) => types.find((t) => t.key === k)?.name ?? k;
    const extras = (t: Props['types'][number]) => modules.filter((m) => !m.core && t.bundle.includes(m.key)).map((m) => m.name);
    const W: [string, string][] = [['product', 'What you sell'], ['category', 'Units / packing'], ['company', 'Who makes it'], ['formula', 'What’s inside'], ['strength', 'Size / variety'],
        ['product_type', 'Kind of item'], ['generic_catalog', 'Ready-made catalogue'], ['supplier', 'Who you buy from'], ['customer', 'Who buys from you'], ['store', 'Your place']];

    return (
        <div className="landing">
            <Head title="Guide: how it works" />
            <LandingHeader brand={brand} signedIn={signedIn} onLanding={false} />

            <section className="g-hero">
                <div className="l-wrap">
                    <span className="eyebrow">Guide</span>
                    <h1>How {brand} works, in plain words</h1>
                    <p>One app for pharmacies, shops and wholesalers. Pick the kind of business you are, switch on the parts you need, and sell. This page explains every part without the jargon.</p>
                    <div className="g-hero-chips">
                        <span><i className="bi bi-check2-circle" />Same app for every business type</span>
                        <span><i className="bi bi-check2-circle" />Switch modules on or off any time</span>
                        <span><i className="bi bi-check2-circle" />{free ? 'Free during launch' : 'Pay only for what you use'}</span>
                    </div>
                </div>
            </section>

            <div className="l-wrap g-layout">
                <aside className="g-toc" aria-label="On this page">
                    <strong>On this page</strong>
                    {SECTIONS.map(([id, label, icon]) => <a key={id} href={`#${id}`} className={active === id ? 'active' : ''}><i className={`bi bi-${icon}`} />{label}</a>)}
                </aside>

                <main className="g-main">
                    <Section id="types" icon="shop" title="Three kinds of business" lead="When you create a business you pick one of these. It decides the words on your screens and which modules your business can have. The choice is final.">
                        <div className="g-cards g-cards-3">
                            {types.map((t) => (
                                <div key={t.key} className="g-card" style={{ ['--accent' as string]: t.accent }}>
                                    <span className="g-icon"><i className={`bi bi-${t.icon}`} /></span>
                                    <h3>{t.name}</h3>
                                    <p className="text-muted mb-2">For {t.examples.join(', ').toLowerCase()}.</p>
                                    <p className="mb-2">{TYPE_STORY[t.key] ?? t.description}</p>
                                    <small className="d-block fw-semibold mb-1">Starts with</small>
                                    <div className="d-flex flex-wrap gap-1">{['Core', ...extras(t)].map((n) => <span key={n} className="g-tag">{n}</span>)}</div>
                                </div>
                            ))}
                        </div>
                        <Tip>In one line: <b>{typeName('pharmacy')}</b> = medicines with batches, expiry and a medicine catalogue. <b>{typeName('inventory')}</b> = a shop selling items at the counter. <b>{typeName('inventory_b2b')}</b> = selling to other businesses with estimates, orders, invoices and bills.</Tip>
                    </Section>

                    <Section id="one-type" icon="lock" title="One business, one type" lead="Each business has exactly one type, chosen once when it is created. It can never be changed or mixed with another type. Need another type? Create another business: one login can own as many as you like.">
                        <div className="g-cards g-cards-3">
                            {types.map((t) => {
                                const own = modules.filter((m) => !m.core && m.allowed_in.includes(t.key));
                                const notHere = modules.filter((m) => !m.core && !m.allowed_in.includes(t.key));
                                return (
                                    <div key={t.key} className="g-card" style={{ ['--accent' as string]: t.accent }}>
                                        <div className="d-flex align-items-center gap-2 mb-2">
                                            <span className="g-icon sm"><i className={`bi bi-${t.icon}`} /></span>
                                            <h3 className="mb-0">{t.name}</h3>
                                        </div>
                                        <small className="d-block fw-semibold mb-2">Modules this business can have</small>
                                        <div className="g-blocks">
                                            {own.map((m) => {
                                                const state = m.locked_in.includes(t.key) ? 'locked' : m.starts_in.includes(t.key) ? 'default' : m.available ? 'optional' : 'soon';
                                                return (
                                                    <div key={m.key} className={`g-block is-${state}`} style={{ ['--accent' as string]: m.accent }} title={m.description}>
                                                        <i className={`bi bi-${m.icon}`} />
                                                        <span>{m.name}</span>
                                                        <em>{state === 'locked' ? 'Part of the type' : state === 'default' ? 'On from the start' : state === 'optional' ? 'Add any time' : 'Coming soon'}</em>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                        {notHere.length > 0 && (
                                            <>
                                                <small className="d-block fw-semibold mt-3 mb-1">Not for this type</small>
                                                <div className="d-flex flex-wrap gap-1">{notHere.map((m) => <span key={m.key} className="g-tag muted"><i className="bi bi-x-circle me-1" />{m.name}</span>)}</div>
                                            </>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                        <Steps items={[
                            ['shop', 'Pick the type when you create the business', 'The wizard shows the three types and asks you to confirm: the choice is final.'],
                            ['lock', 'The type’s own parts are always on', 'For example a pharmacy always has the Pharmacy Pack; every business always has Stock and the POS.'],
                            ['plus-square', 'Add the other modules of your type any time', 'Settings → Modules lists only what your type can have. Switching one off keeps its data.'],
                            ['building-add', 'Another type = another business', 'Use “Create new business” in the business switcher and pick the other type there.'],
                        ]} />
                    </Section>

                    <Section id="tour" icon="signpost-split" title="Demo tour per type" lead="Every step a business of each type goes through, in order. Pick the type.">
                        <div className="g-tabs" role="tablist" aria-label="Business type">
                            {types.map((t) => (
                                <button key={t.key} type="button" role="tab" aria-selected={tourType === t.key} className={`g-tab ${tourType === t.key ? 'active' : ''}`}
                                    style={{ ['--accent' as string]: t.accent }} onClick={() => setTourType(t.key)}><i className={`bi bi-${t.icon} me-1`} />{t.name}</button>
                            ))}
                        </div>
                        {(() => {
                            const t = types.find((x) => x.key === tourType) ?? types[0]!;
                            const own = modules.filter((m) => !m.core && m.allowed_in.includes(t.key));
                            return (
                                <div role="tabpanel" className="mt-3">
                                    <small className="d-block fw-semibold mb-2">{t.name}: modules it can have</small>
                                    <div className="g-blocks mb-3">
                                        {own.map((m) => {
                                            const state = m.locked_in.includes(t.key) ? 'locked' : m.starts_in.includes(t.key) ? 'default' : m.available ? 'optional' : 'soon';
                                            return (
                                                <div key={m.key} className={`g-block is-${state}`} style={{ ['--accent' as string]: m.accent }} title={m.description}>
                                                    <i className={`bi bi-${m.icon}`} /><span>{m.name}</span>
                                                    <em>{state === 'locked' ? 'Part of the type' : state === 'default' ? 'On from the start' : state === 'optional' ? 'Add any time' : 'Coming soon'}</em>
                                                </div>
                                            );
                                        })}
                                    </div>
                                    <Steps items={TOUR[t.key] ?? []} />
                                    {NOT_HERE[t.key] && <Tip>{NOT_HERE[t.key]}</Tip>}
                                </div>
                            );
                        })()}
                    </Section>

                    <Section id="words" icon="translate" title="Same thing, different names" lead="Every business type uses the same screens. Only the names change, so a pharmacy sees “Medicine” where a shop sees “Item”.">
                        <div className="table-responsive g-table">
                            <table className="table mb-0">
                                <thead><tr><th>Means</th>{types.map((t) => <th key={t.key}>{t.name}</th>)}</tr></thead>
                                <tbody>{W.map(([k, means]) => (
                                    <tr key={k}><td className="text-muted">{means}</td>{types.map((t) => <td key={t.key} className="fw-semibold">{wording[t.key]?.[k] ?? '—'}</td>)}</tr>
                                ))}</tbody>
                            </table>
                        </div>
                        <Tip>The names follow your business type, which is fixed when the business is created.</Tip>
                    </Section>

                    <Section id="modules" icon="puzzle" title="Modules: switch on what you need" lead="The app is made of parts called modules. Every business has Core, Stock and the POS. The other modules of your business type can be switched on or off by the owner. Switching one on also switches on what it needs.">
                        <div className="g-cards g-cards-2">
                            {modules.map((m) => (
                                <div key={m.key} className={`g-card g-module ${m.available ? '' : 'is-soon'}`} style={{ ['--accent' as string]: m.accent }}>
                                    <div className="d-flex align-items-center gap-2 mb-1">
                                        <span className="g-icon sm"><i className={`bi bi-${m.icon}`} /></span>
                                        <h3 className="mb-0">{m.name}</h3>
                                        {m.core && <span className="g-tag ms-auto">Always on</span>}
                                        {!m.available && <span className="g-tag warn ms-auto">Coming soon</span>}
                                    </div>
                                    <p className="mb-2">{m.description}</p>
                                    <div className="g-meta">
                                        {!m.core && <span><i className="bi bi-shop" />For {m.allowed_in.length === types.length ? 'every business type' : m.allowed_in.map(typeName).join(', ')}</span>}
                                        {m.needs.length > 0 && <span><i className="bi bi-link-45deg" />Needs {m.needs.join(', ')}</span>}
                                        {!m.core && (m.starts_in.length
                                            ? <span><i className="bi bi-flag" />On from the start for {m.starts_in.map(typeName).join(', ')}</span>
                                            : <span><i className="bi bi-plus-circle" />Add it when you need it</span>)}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <h3 className="g-h3">Getting more modules</h3>
                        <Steps items={[
                            ['person-badge', 'Owner opens Settings → Modules', 'Only the business owner can switch modules on or off.'],
                            ['toggle-on', free ? 'Switch it on: it works straight away' : 'Add it to your plan in Billing', free ? 'During launch every module is free.' : 'After payment it switches on immediately.'],
                            ['archive', 'Switching off keeps your data', 'The screens are hidden until you switch it on again. Nothing is deleted.'],
                        ]} />
                        <Tip>A module can’t be switched off while another one needs it. Example: the POS needs Stock & Purchasing, so Stock stays on while the POS is on.</Tip>
                    </Section>

                    <Section id="selling" icon="cart-check" title="Selling & credit" lead="Every business sells at the counter (POS). A saved customer can pay later or in parts; a walk-in customer always pays in full. Wholesale businesses also sell on documents.">
                        <div className="g-cards g-cards-2">
                            <div className="g-card" style={{ ['--accent' as string]: 'var(--chart-3)' }}>
                                <span className="g-icon"><i className="bi bi-cart-check" /></span>
                                <h3>At the counter (POS)</h3>
                                <p>Scan or search, the app picks the right batch and price, you take the money and print the receipt. In a {typeName('inventory_b2b')} business the same screen is called <b>Invoice</b> and always needs a customer.</p>
                                <ul className="g-list">
                                    <li className="no">Walk-in customer: paid in full on the spot</li>
                                    <li className="yes">Saved customer: switch on <b>Rest on account</b> to take part of the money (or none); the rest goes on their account</li>
                                    <li className="yes">The customer’s credit limit is checked before the sale is saved</li>
                                    <li className="yes">Return of a credit sale = a <b>credit note</b>: it lowers what they owe, no cash is paid out</li>
                                    <li className="yes">Later: Customer accounts → Payments received, statements and receivables ageing</li>
                                </ul>
                            </div>
                            <div className="g-card" style={{ ['--accent' as string]: 'var(--chart-4)' }}>
                                <span className="g-icon"><i className="bi bi-briefcase" /></span>
                                <h3>On documents (B2B Sales, {typeName('inventory_b2b')} only)</h3>
                                <p>For business customers: estimate (quotation) → sales order → invoice → payment later, with shipping and direct credit notes.</p>
                                <ul className="g-list">
                                    <li className="yes">Full credit: the invoice gets a due date from the customer’s payment terms</li>
                                    <li className="yes">Part payments: unpaid → partial → paid, in as many instalments as needed</li>
                                    <li className="yes">Paid too much? The extra stays as the customer’s credit</li>
                                    <li className="yes">Credit limit per customer, opening balance, statements</li>
                                </ul>
                            </div>
                        </div>
                        <Tip>B2B Sales documents belong to <b>{typeName('inventory_b2b')}</b> businesses only.</Tip>
                    </Section>

                    <Section id="buying" icon="truck" title="Buying from suppliers" lead="Every business records purchases and can buy on credit. Wholesale businesses also have purchase orders and direct bills.">
                        <div className="g-cards g-cards-2">
                            <div className="g-card" style={{ ['--accent' as string]: 'var(--chart-2)' }}>
                                <span className="g-icon"><i className="bi bi-box-seam" /></span>
                                <h3>Purchases (everyone)</h3>
                                <p>One screen: supplier, items, cost, sale price, batch and expiry. Save, and the stock is ready to sell.</p>
                                <ul className="g-list">
                                    <li className="yes">Fast: stock arrives immediately</li>
                                    <li className="yes">Mark it <b>paid in full</b>, <b>partly paid</b> (enter the amount) or <b>on credit</b></li>
                                    <li className="yes">Not paid in full? Its <b>bill</b> is kept for you: unpaid → partly paid → paid</li>
                                    <li className="yes">Pay later from Supplier accounts → Bill payments; see statements and payables ageing</li>
                                </ul>
                            </div>
                            <div className="g-card" style={{ ['--accent' as string]: 'var(--chart-7)' }}>
                                <span className="g-icon"><i className="bi bi-truck" /></span>
                                <h3>B2B Purchasing ({typeName('inventory_b2b')} only)</h3>
                                <Steps compact items={[
                                    ['file-earmark-text', 'Purchase order', 'What you ordered. No stock yet.'],
                                    ['box-arrow-in-down', 'Goods received', 'Stock arrives (it shows under Purchases too).'],
                                    ['receipt', 'Bill', 'What you owe, with a due date.'],
                                    ['cash-coin', 'Pay the bill', 'All at once or in parts.'],
                                ]} />
                            </div>
                        </div>
                        <Tip>Every business: a purchase on credit gets its bill automatically. Only a {typeName('inventory_b2b')} business enters purchase orders and bills on their own.</Tip>
                    </Section>

                    <Section id="stock" icon="calendar2-check" title="Stock, batches & expiry" lead="Every purchase becomes a batch: its own quantity, cost, price and (if it can expire) expiry date. Stock is always counted batch by batch.">
                        <div className="g-cards g-cards-2">
                            <div className="g-card"><h3><i className="bi bi-hourglass-split me-2" />Can this item expire?</h3>
                                <p>Set on each item. If yes, every purchase of it must have the expiry date printed on the pack. If no, the date is optional. Pharmacy items start as “can expire”; shop items start as “can’t expire”.</p></div>
                            <div className="g-card"><h3><i className="bi bi-x-octagon me-2" />Expired stock is never sold</h3>
                                <p>A batch can be sold until the day before its expiry date. Batches without an expiry date never expire.</p></div>
                        </div>
                        <h3 className="g-h3">Which batch is sold first?</h3>
                        <div className="g-cards g-cards-3">
                            <Mini icon="hourglass-split" title="Earliest expiry first (FEFO)" text="Sells the batch that expires soonest. Best for medicines and food." />
                            <Mini icon="arrow-right-circle" title="Oldest purchase first (FIFO)" text="Sells the batch you bought first." />
                            <Mini icon="shuffle" title="Any batch" text="No order: the batch with the most stock left goes first." />
                        </div>
                        <Steps items={[
                            ['building', 'Business-wide setting', 'Settings → Sale preferences. Applies to every item.'],
                            ['tag', 'Item’s own setting', 'An item can override it on its Sale preferences page.'],
                            ['upc-scan', 'The pack in the customer’s hand', 'At the POS, pick the batch number printed on the pack. That exact batch is sold first.'],
                        ]} />
                        <Tip>The customer picked a different pack than the one the rule would sell? Choose its batch in the cart row’s <b>Batch</b> field. Shelf and system stay in step.</Tip>
                    </Section>

                    <Section id="packaging" icon="boxes" title="Packaging & prices" lead="Tell the app how an item is packed, from the biggest unit to the smallest you sell. Stock is counted in the smallest unit.">
                        <div className="g-chain">
                            <span className="g-unit">1 Carton</span><i className="bi bi-arrow-right" /><span className="g-unit">12 Boxes</span><i className="bi bi-arrow-right" /><span className="g-unit">120 Pieces</span>
                        </div>
                        <Steps items={[
                            ['search', 'Open the item', 'Items → click it → Packaging & prices.'],
                            ['diagram-3', 'Build the chain', 'Largest unit first, then “how many in one …”. Missing a unit? Use “New unit” right there.'],
                            ['currency-exchange', 'Set prices', 'Purchase and sale price per unit; the app fills smaller units for you.'],
                        ]} />
                        <div className="g-example">
                            <strong>How profit is shown</strong>
                            <p className="mb-0">Bought for 100, sold for 200 → profit 100 = <b>100% on cost</b>. (As a share of the sale price that is 50%; hover the number to see it.)</p>
                        </div>
                        <Tip>Once an item has purchases, stock or sales, its packaging is locked so stock counts never change meaning. Prices stay editable.</Tip>
                    </Section>

                    <Section id="team" icon="people" title="Team & roles" lead="Invite people by email and give each a role.">
                        <div className="g-cards g-cards-3">
                            <Mini icon="person-check" title="Owner" text="Everything in the business, including modules and billing." />
                            <Mini icon="cart" title="Sales person" text="Sells at the POS, gives discounts within limits, reprints receipts." />
                            <Mini icon="sliders" title="Your own roles" text="Pick exactly which screens and actions a role may use." />
                        </div>
                        <Tip>One login can belong to several businesses. Switch between them from the business switcher; each has its own stock, invoices and team.</Tip>
                    </Section>

                    <Section id="faq" icon="question-circle" title="Quick answers">
                        <div className="g-faq">
                            {FAQ.map(([q, a]) => <details key={q}><summary>{q}</summary><p>{a}</p></details>)}
                        </div>
                    </Section>

                    <div className="g-cta">
                        <h2>Ready to try it?</h2>
                        <p>Set up your business in a few minutes{free ? ', free during launch' : ''}.</p>
                        {signedIn ? <a href={route('dashboard')} className="btn btn-primary btn-lg">Open your dashboard</a> : <Link href={route('signup')} className="btn btn-primary btn-lg">Get started</Link>}
                    </div>
                </main>
            </div>
            <Toaster />
        </div>
    );
}

Guide.layout = (page: ReactNode) => page;

const TYPE_STORY: Record<string, string> = {
    pharmacy: 'Sells medicines at the counter. Every medicine has batches and expiry dates; the oldest-expiring stock sells first. Comes with a ready-made generic medicine catalogue.',
    inventory: 'Sells items at the counter to walk-in customers, paid on the spot. Can keep stock in several stores or warehouses.',
    inventory_b2b: 'Sells to other businesses with quotations, orders and invoices they pay later. Buys with purchase orders and bills. Keeps full accounts.',
};

/** Demo tour per type (same content as docs/DEMO_GUIDE.md). [icon, title, text] */
const COMMON_START: [string, string, string][] = [
    ['person-plus', 'Sign up and create the business', 'Get started → pick the type → set a password. On the first sign-in the wizard asks for the business type and to confirm it is final.'],
    ['puzzle', 'Check the modules', 'Settings → Modules: the type’s own modules are locked on; micro services can be switched off and on any time.'],
];
const COMMON_MONEY: [string, string, string][] = [
    ['box-arrow-in-down', 'Buy stock: paid, partly paid or on credit', 'Purchases → Add purchase. Not paid in full? Its bill is kept; pay it later from Supplier accounts → Bill payments.'],
    ['cart-check', 'Sell: walk-in or saved customer', 'Walk-in customers pay in full. Pick a saved customer and switch on “Rest on account” to sell on credit; their credit limit is checked.'],
    ['arrow-return-left', 'Returns', 'A cash sale gives the cash back. A credit sale becomes a credit note: the customer owes less.'],
    ['cash-coin', 'Get paid and pay', 'Customer accounts → Payments received; Supplier accounts → Bill payments. Statements and ageing show who owes what.'],
    ['buildings', 'Warehouses', 'Add a branch store, transfer stock, write off damaged stock, choose which store the POS sells from.'],
    ['journal-text', 'Books', 'Every sale, purchase, payment and expense posts itself. Switching Books on later converts the history in the background while you keep working.'],
    ['wallet2', 'Expenses', 'Record an expense with its receipt, approve it, reimburse the employee.'],
    ['graph-up-arrow', 'Reports', 'Sales, profit & loss, expiry and stock alerts; Advanced Reporting when added.'],
];
const TOUR: Record<string, [string, string, string][]> = {
    pharmacy: [...COMMON_START,
        ['capsule', 'Medicines', 'Import from Generic Medicines or add your own; set the packaging (Box → Strip → Tablet) and prices. Medicines can expire by default; FEFO sells the earliest expiry first.'],
        ...COMMON_MONEY],
    inventory: [...COMMON_START,
        ['box-seam', 'Items', 'Add items with brand, variant and units of measure. Items can’t expire unless you switch it on (e.g. food).'],
        ...COMMON_MONEY],
    inventory_b2b: [...COMMON_START,
        ['person-vcard', 'Customers', 'Customer accounts with payment terms, credit limit and opening balance.'],
        ['receipt', 'Invoice screen', 'Same layout as a POS, but pick the customer first: no walk-in. Paid in full, part paid or on account.'],
        ['file-earmark-text', 'Estimate → order → invoice', 'Quotation → sales order → invoice. Issuing takes the stock and asks what was paid now: nothing, in full or part.'],
        ['truck', 'Shipping', 'Add a shipment to the order or invoice (carrier, tracking, packages), mark shipped and delivered, print the delivery note.'],
        ['cart-plus', 'Purchase order → goods received → bill', 'Order from the vendor, receive the goods into stock, enter the bill and pay it now or later. A supplier bill without an order: Bills → Enter a supplier bill.'],
        ...COMMON_MONEY.filter(([icon]) => icon !== 'cart-check')],
};
const NOT_HERE: Record<string, string> = {
    pharmacy: 'Not in a pharmacy: estimates, sales / purchase orders and direct bills. For wholesale, create a second business of type Wholesale & Distribution.',
    inventory: 'Not in a shop: the Pharmacy Pack and the B2B documents (estimates, sales / purchase orders, direct bills).',
    inventory_b2b: 'Not in a wholesale business: the Pharmacy Pack (generic medicine catalogue, formulas, strengths).',
};

const FAQ: [string, string][] = [
    ['Can a pharmacy also do wholesale (B2B)?', 'Not in the same business: a business keeps the type it was created with. Create a second business of type Wholesale & Distribution; one login can run both.'],
    ['Can I change my business type later?', 'No. The type is chosen once, when the business is created. To work as another type, create a new business.'],
    ['Can a customer pay later at the counter?', 'Yes, if they are a saved customer: pick them at the POS and switch on “Rest on account”. Walk-in customers always pay in full. Their credit limit is checked.'],
    ['Can I buy from a supplier and pay later?', 'Yes, in every business: mark the purchase “On credit” or “Partly paid”. Its bill is kept and you pay it later from Supplier accounts → Bill payments.'],
    ['What happens when a credit sale is returned?', 'A credit note is made automatically: it lowers what the customer owes. If it is more than they owe, the rest stays as their credit.'],
    ['What happens to my data if I switch a module off?', 'Nothing is deleted. Its screens are hidden until you switch it on again.'],
    ['What if an item has no expiry date?', 'Mark it as “can’t expire” on the item. Purchases won’t ask for a date and its stock never expires.'],
    ['Can I run more than one business?', 'Yes. One login, as many businesses as you need, each with its own stock, team and settings.'],
    ['Is FBR supported?', 'FBR integration is coming soon. Invoice numbering already works with or without FBR.'],
];

function Section({ id, icon, title, lead, children }: { id: string; icon: string; title: string; lead?: string; children: ReactNode }) {
    return (
        <section id={id} className="g-section">
            <div className="g-section-head"><span className="g-icon"><i className={`bi bi-${icon}`} /></span><div><h2>{title}</h2>{lead && <p>{lead}</p>}</div></div>
            {children}
        </section>
    );
}
function Tip({ children }: { children: ReactNode }) {
    return <div className="g-tip"><i className="bi bi-lightbulb" /><div>{children}</div></div>;
}
function Mini({ icon, title, text }: { icon: string; title: string; text: string }) {
    return <div className="g-card"><h3><i className={`bi bi-${icon} me-2`} />{title}</h3><p className="mb-0">{text}</p></div>;
}
function Steps({ items, compact }: { items: [string, string, string][]; compact?: boolean }) {
    return (
        <ol className={`g-steps ${compact ? 'compact' : ''}`}>
            {items.map(([icon, title, text], i) => (
                <li key={title}><span className="g-num">{i + 1}</span><div><strong><i className={`bi bi-${icon} me-1`} />{title}</strong><p>{text}</p></div></li>
            ))}
        </ol>
    );
}
