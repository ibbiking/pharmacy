const STATUS: Record<string, [string, string]> = {
    packed: ['Packed', 'var(--chart-8)'], shipped: ['Shipped', 'var(--chart-1)'], delivered: ['Delivered', 'var(--app-success)'],
    draft: ['Draft', 'var(--chart-8)'], sent: ['Sent', 'var(--chart-1)'], accepted: ['Accepted', 'var(--app-success)'], declined: ['Declined', 'var(--app-danger)'],
    expired: ['Expired', 'var(--app-warning)'], converted: ['Converted', 'var(--chart-6)'], open: ['Open', 'var(--chart-1)'], invoiced: ['Invoiced', 'var(--app-success)'],
    billed: ['Billed', 'var(--app-success)'], partially_received: ['Partly received', 'var(--app-warning)'], received: ['Received', 'var(--app-success)'],
    cancelled: ['Cancelled', 'var(--chart-8)'], unpaid: ['Unpaid', 'var(--app-warning)'], partial: ['Partly paid', 'var(--chart-3)'], paid: ['Paid', 'var(--app-success)'],
    overdue: ['Overdue', 'var(--app-danger)'], void: ['Void', 'var(--chart-8)'], open_credit: ['Credit open', 'var(--chart-3)'], applied: ['Applied', 'var(--app-success)'],
    submitted: ['Submitted', 'var(--chart-1)'], approved: ['Approved', 'var(--app-success)'], rejected: ['Rejected', 'var(--app-danger)'], reimbursed: ['Reimbursed', 'var(--chart-6)'],
    posted: ['Posted', 'var(--app-success)'],
};

export const statusLabel = (s: string) => STATUS[s]?.[0] ?? s;

export function statusPill(s: string) {
    const [label, accent] = STATUS[s] ?? [s, 'var(--chart-8)'];
    return <span className="pill" style={{ ['--accent' as string]: accent }}>{label}</span>;
}
