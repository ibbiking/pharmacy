export type ThemePref = 'light' | 'dark' | 'system';

export interface NavItem {
    label: string;
    icon?: string;
    href?: string;
    inertia: boolean;
    active: boolean;
    locked?: boolean;
    children?: NavItem[];
}

export interface SharedProps {
    auth: {
        user: null | {
            id: number;
            name: string;
            email: string;
            avatar: string | null;
            roles: string[];
            theme: ThemePref;
            is_owner: boolean;
        };
    };
    business: null | { id: number; name: string; type: string; currency: string; impersonating: boolean };
    businesses: null | { items: { id: number; name: string }[]; count: number };
    wording: Record<string, string>;
    navigation: { group: string; items: NavItem[] }[];
    activeModules: string[];
    brand: string;
    unreadNotifications: number;
    flash: { success?: string | null; error?: string | null; message?: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

/** Laravel LengthAwarePaginator JSON. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url?: string | null;
    next_page_url?: string | null;
}

export interface TableFilters {
    q?: string;
    sort?: string;
    dir?: 'asc' | 'desc';
    per_page?: number;
}
