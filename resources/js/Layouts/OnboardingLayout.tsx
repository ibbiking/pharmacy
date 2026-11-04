import { router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Toaster from '@/Components/Toaster';

/** Focused full-screen layout for onboarding flows (create business). */
export default function OnboardingLayout({ children }: { children: ReactNode }) {
    const { auth, business } = usePage().props;

    return (
        <div className="onboarding-shell">
            <header className="onboarding-top">
                <span className="brand-mark">
                    <i className="bi bi-capsule-pill" />
                </span>
                <strong>PharmaSuite</strong>
                <div className="ms-auto d-flex gap-2 align-items-center">
                    {business && (
                        <a className="btn btn-sm btn-light" href={route('dashboard')}>
                            <i className="bi bi-arrow-left me-1" />
                            Back to {business.name}
                        </a>
                    )}
                    {auth.user && (
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => router.post(route('logout'))}>
                            Log out
                        </button>
                    )}
                </div>
            </header>
            <main className="onboarding-main">{children}</main>
            <Toaster />
        </div>
    );
}
