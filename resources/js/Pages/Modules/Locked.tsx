import { Head, Link, usePage } from '@inertiajs/react';

export default function ModuleLocked({ module }: { module: { key: string; name: string; description: string; icon: string; accent: string } }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={`${module.name} is off`} />
            <div className="app-card section-card mx-auto mt-4" style={{ maxWidth: 560, ['--accent' as string]: module.accent }}>
                <div className="section-body text-center p-5">
                    <div className="empty-state p-0">
                        <div className="bubble" style={{ color: module.accent }}>
                            <i className={`bi bi-${module.icon}`} />
                        </div>
                    </div>
                    <h1 className="h4 fw-bold mb-2">
                        <i className="bi bi-lock-fill me-2 text-muted" />
                        {module.name} is not switched on
                    </h1>
                    <p className="text-muted mb-4">{module.description}</p>
                    {auth.user?.is_owner ? (
                        <Link href={route('modules.index', { module: module.key })} className="btn btn-primary">
                            <i className="bi bi-puzzle me-1" />
                            Open Modules to switch it on
                        </Link>
                    ) : (
                        <p className="mb-0 small">Ask the business owner to switch it on.</p>
                    )}
                </div>
            </div>
        </>
    );
}
