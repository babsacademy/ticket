export function formatCurrency(amount: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
}

export function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'full',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export function formatShortDate(iso: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}
