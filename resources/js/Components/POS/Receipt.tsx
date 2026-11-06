/**
 * The live POS receipt. Same markup and classes (`pmx-*`, resources/css/receipt.css) as the printed receipt
 * (admin/pos/receipt-print.blade.php), so screen and paper match. Every number comes from the server quote.
 */
export interface ReceiptLine { key: string; name: string; type?: string | null; qty: number; unit?: string | null; price: number; discountLabel: string | null; total: number; problem?: boolean }
export interface ReceiptTax { kind: 'additive' | 'gst' | 'included'; name: string; rate: number; amount: number }
export interface ReceiptTotals { subtotal: number; line_discounts: number; net: number; invoice_discount: number; grand_total: number; cash_received: number; change: number; included_tax: number | null }

interface Props {
    business: { name: string; address: string | null; phone: string | null; note: string | null };
    invoiceNo: string | null;
    fbr: boolean;
    date: Date;
    lines: ReceiptLine[];
    totals: ReceiptTotals | null;
    taxes: ReceiptTax[];
    invoiceDiscountLabel: string | null;
}

const n = (v: number) => v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const when = (d: Date) => d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: '2-digit', hour: 'numeric', minute: '2-digit', hour12: true }).replace(',', '');

export default function Receipt({ business, invoiceNo, fbr, date, lines, totals, taxes, invoiceDiscountLabel }: Props) {
    const additive = taxes.filter((t) => t.kind === 'additive');
    const gst = taxes.filter((t) => t.kind === 'gst');
    const included = taxes.filter((t) => t.kind === 'included');

    return (
        <div className="pmx-receipt" aria-label="Receipt preview">
            <div className="pmx-header">
                <h1 className="pmx-brand">{business.name}</h1>
                {business.address && <div className="pmx-brand-line">{business.address}</div>}
                <div className="pmx-doc-title">Sale Receipt</div>
            </div>
            <div className="pmx-meta">
                <div className="pmx-row"><span className="pmx-label">Invoice #</span><span className="pmx-value">{invoiceNo ?? (fbr ? 'Pending (FBR)' : '—')}</span></div>
                <div className="pmx-row"><span className="pmx-label">Date</span><span className="pmx-value">{when(date)}</span></div>
            </div>
            <div className="pmx-divider" />
            <table className="pmx-items">
                <thead><tr><th className="col-sr">#</th><th className="col-item">Item</th><th className="col-qty">Qty</th><th className="col-price">Price</th><th className="col-disc">Disc</th><th className="col-total">Total</th></tr></thead>
                <tbody>
                    {lines.length === 0 ? <tr><td colSpan={6} className="pmx-empty">No items in cart</td></tr> : lines.map((l, i) => (
                        <tr key={l.key} className={`pmx-line-in ${l.problem ? 'pmx-line-problem' : ''}`}>
                            <td className="col-sr">{i + 1}</td>
                            <td className="col-item"><span className="item-name">{l.name}</span>{(l.type || l.unit) && <span className="item-type">{[l.unit, l.type].filter(Boolean).join(' · ')}</span>}</td>
                            <td className="col-qty">{l.qty}</td>
                            <td className="col-price">{n(l.price)}</td>
                            <td className="col-disc">{l.discountLabel ?? '–'}</td>
                            <td className="col-total">{n(l.total)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {totals && lines.length > 0 && <>
                <div className="pmx-divider" />
                <div className="pmx-totals">
                    <div className="pmx-row"><span className="pmx-label">Subtotal</span><span className="pmx-value">{n(totals.subtotal)}</span></div>
                    {totals.line_discounts > 0 && <>
                        <div className="pmx-row pmx-row--muted"><span className="pmx-label">Product Discounts</span><span className="pmx-value">-{n(totals.line_discounts)}</span></div>
                        <div className="pmx-row"><span className="pmx-label">Net Subtotal</span><span className="pmx-value">{n(totals.net)}</span></div>
                    </>}
                    {totals.invoice_discount > 0 && <div className="pmx-row pmx-row--muted"><span className="pmx-label">Invoice Discount {invoiceDiscountLabel}</span><span className="pmx-value">-{n(totals.invoice_discount)}</span></div>}
                    {additive.map((t) => <div key={t.name} className="pmx-row"><span className="pmx-label">{t.name} ({n(t.rate)}%)</span><span className="pmx-value">+{n(t.amount)}</span></div>)}
                    <div className="pmx-row pmx-row--grand"><span className="pmx-label">Grand Total</span><span className="pmx-value">{n(totals.grand_total)}</span></div>
                    <div className="pmx-row" style={{ marginTop: 6 }}><span className="pmx-label">Cash Received</span><span className="pmx-value">{n(totals.cash_received)}</span></div>
                    <div className="pmx-row"><span className="pmx-label">Change Return</span><span className="pmx-value">{n(totals.change)}</span></div>
                </div>
            </>}
            {gst.length > 0 && <>
                <div className="pmx-divider" />
                <div className="pmx-totals">
                    <div className="pmx-note">GST Disclosure (already included in price above, not an extra charge)</div>
                    {gst.map((t) => <div key={t.name} className="pmx-row pmx-row--muted"><span className="pmx-label">{t.name} ({n(t.rate)}%)</span><span className="pmx-value">{n(t.amount)}</span></div>)}
                </div>
            </>}
            {included.length > 0 && (
                // Owner-only (D3): not printed on the customer's receipt.
                <div className="pmx-owner-only">
                    <div className="pmx-note">Owner only, not printed: tax inside the prices</div>
                    {included.map((t) => <div key={t.name} className="pmx-row pmx-row--muted"><span className="pmx-label">{t.name} ({n(t.rate)}%)</span><span className="pmx-value">{n(t.amount)}</span></div>)}
                </div>
            )}
            <div className="pmx-divider" />
            <div className="pmx-footer">
                {business.note && <div>{business.note}</div>}
                <div className="pmx-thanks">Thank you for your purchase!</div>
                <div>Please visit again</div>
                {business.phone && <div>Contact: {business.phone}</div>}
            </div>
        </div>
    );
}
