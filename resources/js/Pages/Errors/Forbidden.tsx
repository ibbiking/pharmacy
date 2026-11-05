import { Head, Link } from '@inertiajs/react';

export default function Forbidden({ permission, canSell }: { permission: string; canSell: boolean }) {
    return (
        <>
            <Head title="No access" />
            <div className="app-card section-card mx-auto mt-4" style={{ maxWidth: 560, ['--accent' as string]: 'var(--app-warning)' }}>
                <div className="section-body text-center p-5">
                    <div className="empty-state p-0">
                        <div className="bubble" style={{ color: 'var(--app-warning)', background: 'var(--app-warning-soft)' }}>
                            <i className="bi bi-shield-lock" />
                        </div>
                    </div>
                    <h1 className="h4 fw-bold mb-2">You don’t have access to this</h1>
                    <p className="text-muted mb-1">Your role in this business doesn’t include:</p>
                    <p className="fw-semibold mb-4">{permission}</p>
                    <p className="small text-muted">Ask the business owner to add it to your role (Settings → Roles).</p>
                    <div className="d-flex justify-content-center gap-2">
                        {canSell && <a href={route('pos.index')} className="btn btn-primary"><i className="bi bi-cart-check me-1" />Go to POS</a>}
                        <Link href={route('help.index')} className="btn btn-light"><i className="bi bi-life-preserver me-1" />Help Center</Link>
                    </div>
                </div>
            </div>
        </>
    );
}
