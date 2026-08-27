import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/admin/events';

type EventListItem = {
    id: number;
    title: string;
    date: string;
    venue: string;
    city: string | null;
    status: string;
    capacity: number;
    organizer: string;
    tickets_sold: number;
    remaining_capacity: number;
    revenue: number;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedEvents = {
    data: EventListItem[];
    links: PaginationLink[];
};

const STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'secondary',
    published: 'default',
    cancelled: 'destructive',
    ended: 'outline',
};

const STATUS_LABELS: Record<string, string> = {
    draft: 'Brouillon',
    published: 'Publié',
    cancelled: 'Annulé',
    ended: 'Terminé',
};

function formatCurrency(amount: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
}

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export default function EventsIndex({ events }: { events: PaginatedEvents }) {
    return (
        <>
            <Head title="Événements" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Événements"
                        description="Gérer les événements de la plateforme"
                    />
                    <Button asChild>
                        <Link href={create()}>Créer un événement</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Événement
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Organisateur
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Statut
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Billets vendus
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Places restantes
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Revenus
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {events.data.map((event) => (
                                <tr
                                    key={event.id}
                                    className="transition-colors hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(event.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {event.title}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {formatDate(event.date)} ·{' '}
                                            {event.venue}
                                            {event.city
                                                ? `, ${event.city}`
                                                : ''}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {event.organizer}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                STATUS_VARIANTS[event.status] ??
                                                'outline'
                                            }
                                        >
                                            {STATUS_LABELS[event.status] ??
                                                event.status}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {event.tickets_sold} / {event.capacity}
                                    </td>
                                    <td className="px-4 py-3">
                                        {event.remaining_capacity}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatCurrency(event.revenue)}
                                    </td>
                                </tr>
                            ))}

                            {events.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Aucun événement pour le moment.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {events.links.length > 3 && (
                    <div className="flex flex-wrap items-center gap-1">
                        {events.links.map((link, i) => (
                            <Button
                                key={i}
                                asChild={link.url !== null}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={link.url === null}
                            >
                                {link.url !== null ? (
                                    <Link
                                        href={link.url}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Événements',
            href: index(),
        },
    ],
};
