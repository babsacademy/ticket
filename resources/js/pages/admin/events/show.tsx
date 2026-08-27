import { Head, Link } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/admin/events';

type EventDetails = {
    id: number;
    title: string;
    description: string | null;
    date: string;
    venue: string;
    city: string | null;
    capacity: number;
    cover_image: string | null;
    status: string;
    organizer: string;
    ticket_types: { id: number; name: string; price: number }[];
};

type TicketRow = {
    id: number;
    holder_name: string;
    holder_email: string;
    ticket_type: string;
    scanned_at: string | null;
    scanned_by: string | null;
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

function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export default function EventsShow({
    event,
    tickets,
}: {
    event: EventDetails;
    tickets: TicketRow[];
}) {
    return (
        <>
            <Head title={event.title} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={event.title}
                        description={`${formatDateTime(event.date)} · ${event.venue}${event.city ? `, ${event.city}` : ''}`}
                    />
                    <Button asChild variant="outline">
                        <Link href={EventController.edit(event.id)}>
                            Modifier
                        </Link>
                    </Button>
                </div>

                {event.cover_image && (
                    <img
                        src={`/storage/${event.cover_image}`}
                        alt={event.title}
                        className="max-h-64 w-full rounded-xl border object-cover"
                    />
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-normal text-muted-foreground">
                                Statut
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Badge>
                                {STATUS_LABELS[event.status] ?? event.status}
                            </Badge>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-normal text-muted-foreground">
                                Organisateur
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="font-medium">
                            {event.organizer}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-normal text-muted-foreground">
                                Capacité
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="font-medium">
                            {event.capacity} places
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-normal text-muted-foreground">
                                Billets vendus
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="font-medium">
                            {tickets.length} / {event.capacity}
                        </CardContent>
                    </Card>
                </div>

                {event.description && (
                    <p className="text-sm text-muted-foreground">
                        {event.description}
                    </p>
                )}

                <div className="space-y-2">
                    <Heading variant="small" title="Types de billets" />
                    <div className="flex flex-wrap gap-2">
                        {event.ticket_types.map((ticketType) => (
                            <Badge key={ticketType.id} variant="outline">
                                {ticketType.name} —{' '}
                                {formatCurrency(ticketType.price)}
                            </Badge>
                        ))}
                    </div>
                </div>

                <div className="space-y-2">
                    <Heading variant="small" title="Billets vendus" />

                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Participant
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Type de billet
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Statut
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {tickets.map((ticket) => (
                                    <tr key={ticket.id}>
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {ticket.holder_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {ticket.holder_email}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {ticket.ticket_type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {ticket.scanned_at ? (
                                                <Badge variant="default">
                                                    Scanné
                                                    {ticket.scanned_by
                                                        ? ` · ${ticket.scanned_by}`
                                                        : ''}
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Non scanné
                                                </Badge>
                                            )}
                                        </td>
                                    </tr>
                                ))}

                                {tickets.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={3}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            Aucun billet vendu pour le moment.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

EventsShow.layout = {
    breadcrumbs: [{ title: 'Événements', href: index() }],
};
