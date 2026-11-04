export function formatNumber(value: number | string | null | undefined, digits = 2): string {
    const n = Number(value ?? 0);
    return n.toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits });
}

export function formatMoney(value: number | string | null | undefined, currency = ''): string {
    return `${currency ? `${currency} ` : ''}${formatNumber(value, 2)}`;
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]!.toUpperCase())
        .join('');
}

export function cssVar(name: string): string {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}
