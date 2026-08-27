import { Head, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { ticketPdf } from '@/actions/App/Http/Controllers/CheckoutController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatCurrency, formatDateTime } from '@/lib/format';

type OrderSummary = {
    confirmation_token: string;
    buyer_name: string | null;
    buyer_phone: string | null;
    total_amount: number;
    status: string;
};

type EventSummary = {
    title: string;
    date: string;
    venue: string;
    city: string | null;
};

type OrderItemSummary = {
    ticket_type: string;
    quantity: number;
    unit_price: number;
};

type TicketSummary = {
    id: number;
    holder_name: string;
    ticket_type: string;
};

export default function CheckoutConfirmation({
    order,
    event,
    items,
    tickets,
}: {
    order: OrderSummary;
    event: EventSummary;
    items: OrderItemSummary[];
    tickets: TicketSummary[];
}) {
    const ticketsReady = tickets.length > 0;

    // Poll for the tickets prop every 3s until generation finishes in the background.
    const { stop } = usePoll(3000, { only: ['tickets'] });

    useEffect(() => {
        if (ticketsReady) {
            stop();
        }
    }, [ticketsReady, stop]);

    return (
        <>
            <Head title="Confirmation de commande" />

            <div className="space-y-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Merci{order.buyer_name ? `, ${order.buyer_name}` : ''} !
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Votre commande pour {event.title} est confirmée.
                    </p>
                </div>

                <Alert>
                    <AlertTitle className="flex items-center gap-2">
                        {!ticketsReady && <Spinner className="size-4" />}
                        {ticketsReady
                            ? 'Votre billet a été envoyé'
                            : 'Votre billet est en cours de génération'}
                    </AlertTitle>
                    <AlertDescription>
                        {ticketsReady
                            ? 'Vos billets ont été générés et envoyés par WhatsApp, SMS ou email.'
                            : 'Vos billets seront envoyés par WhatsApp, SMS ou email dans quelques instants. Cette page se met à jour automatiquement.'}
                        {order.buyer_phone ? ` (${order.buyer_phone})` : ''}
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <CardTitle>Récapitulatif de la commande</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div>
                            <div className="font-medium">{event.title}</div>
                            <div className="text-sm text-muted-foreground">
                                {formatDateTime(event.date)} · {event.venue}
                                {event.city ? `, ${event.city}` : ''}
                            </div>
                        </div>

                        <div className="divide-y">
                            {items.map((item, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between py-2"
                                >
                                    <span>
                                        {item.quantity} × {item.ticket_type}
                                    </span>
                                    <span>
                                        {formatCurrency(
                                            item.quantity * item.unit_price,
                                        )}
                                    </span>
                                </div>
                            ))}
                        </div>

                        <div className="flex items-center justify-between border-t pt-4 font-semibold">
                            <span>Total</span>
                            <span>{formatCurrency(order.total_amount)}</span>
                        </div>
                    </CardContent>
                </Card>

                {ticketsReady && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Vos billets</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {tickets.map((ticket) => (
                                <div
                                    key={ticket.id}
                                    className="flex items-center justify-between border-b pb-3 last:border-b-0 last:pb-0"
                                >
                                    <span>{ticket.holder_name}</span>
                                    <Badge variant="outline">
                                        {ticket.ticket_type}
                                    </Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {ticketsReady && (
                    <Button asChild size="lg" className="w-full">
                        <a href={ticketPdf(order.confirmation_token).url}>
                            Télécharger mes billets
                        </a>
                    </Button>
                )}
            </div>
        </>
    );
}
