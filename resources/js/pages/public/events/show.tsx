import { Form, Head } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import CheckoutController from '@/actions/App/Http/Controllers/CheckoutController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type EventDetails = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    date: string;
    venue: string;
    city: string | null;
    cover_image: string | null;
};

type TicketTypeOption = {
    id: number;
    name: string;
    price: number;
    remaining: number;
};

function formatCurrency(amount: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
}

function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'full',
        timeStyle: 'short',
    }).format(new Date(iso));
}

// Must match StoreCheckoutRequest::MAX_QUANTITY_PER_TYPE.
const MAX_QUANTITY_PER_TYPE = 10;

export default function PublicEventShow({
    event,
    ticketTypes,
    paymentStatus,
}: {
    event: EventDetails;
    ticketTypes: TicketTypeOption[];
    paymentStatus: 'success' | 'error' | null;
}) {
    const [quantities, setQuantities] = useState<Record<number, number>>({});

    function setQuantity(
        ticketTypeId: number,
        remaining: number,
        value: number,
    ) {
        const clamped = Math.max(
            0,
            Math.min(remaining, MAX_QUANTITY_PER_TYPE, Math.floor(value) || 0),
        );

        setQuantities((current) => ({ ...current, [ticketTypeId]: clamped }));
    }

    const selectedItems = ticketTypes
        .map((ticketType) => ({
            ticketType,
            quantity: quantities[ticketType.id] ?? 0,
        }))
        .filter((item) => item.quantity > 0);

    const total = selectedItems.reduce(
        (sum, item) => sum + item.quantity * item.ticketType.price,
        0,
    );

    return (
        <>
            <Head title={event.title} />

            <div className="space-y-6">
                {paymentStatus === 'error' && (
                    <Alert variant="destructive">
                        <AlertTitle>Paiement annulé</AlertTitle>
                        <AlertDescription>
                            Le paiement n&apos;a pas abouti. Vous pouvez
                            réessayer ci-dessous.
                        </AlertDescription>
                    </Alert>
                )}

                {paymentStatus === 'success' && (
                    <Alert>
                        <AlertTitle>Paiement reçu</AlertTitle>
                        <AlertDescription>
                            Merci ! Vos billets vous seront envoyés par email,
                            WhatsApp ou SMS dès leur génération.
                        </AlertDescription>
                    </Alert>
                )}

                {event.cover_image && (
                    <img
                        src={`/storage/${event.cover_image}`}
                        alt={event.title}
                        className="max-h-72 w-full rounded-xl border object-cover"
                    />
                )}

                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {event.title}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {formatDateTime(event.date)} · {event.venue}
                        {event.city ? `, ${event.city}` : ''}
                    </p>
                </div>

                {event.description && (
                    <p className="text-sm">{event.description}</p>
                )}

                <Form
                    {...CheckoutController.store.form(event.slug)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Billets</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {ticketTypes.map((ticketType) => (
                                        <div
                                            key={ticketType.id}
                                            className="flex items-center justify-between gap-4 border-b pb-4 last:border-b-0 last:pb-0"
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {ticketType.name}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {formatCurrency(
                                                        ticketType.price,
                                                    )}{' '}
                                                    ·{' '}
                                                    {ticketType.remaining > 0
                                                        ? `${ticketType.remaining} place(s) restante(s)`
                                                        : 'Complet'}
                                                </div>
                                            </div>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={Math.min(
                                                    ticketType.remaining,
                                                    MAX_QUANTITY_PER_TYPE,
                                                )}
                                                disabled={
                                                    ticketType.remaining === 0
                                                }
                                                value={
                                                    quantities[ticketType.id] ??
                                                    0
                                                }
                                                onChange={(e) =>
                                                    setQuantity(
                                                        ticketType.id,
                                                        ticketType.remaining,
                                                        Number(e.target.value),
                                                    )
                                                }
                                                className="w-20"
                                            />
                                        </div>
                                    ))}
                                    <InputError message={errors.items} />
                                </CardContent>
                            </Card>

                            {selectedItems.map((item, index) => (
                                <Fragment key={item.ticketType.id}>
                                    <input
                                        type="hidden"
                                        name={`items[${index}][ticket_type_id]`}
                                        value={item.ticketType.id}
                                    />
                                    <input
                                        type="hidden"
                                        name={`items[${index}][quantity]`}
                                        value={item.quantity}
                                    />
                                </Fragment>
                            ))}

                            <Card>
                                <CardHeader>
                                    <CardTitle>Vos informations</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="buyer_name">
                                            Nom complet
                                        </Label>
                                        <Input
                                            id="buyer_name"
                                            name="buyer_name"
                                            required
                                        />
                                        <InputError
                                            message={errors.buyer_name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="buyer_email">
                                            Email (optionnel)
                                        </Label>
                                        <Input
                                            id="buyer_email"
                                            name="buyer_email"
                                            type="email"
                                            placeholder="vous@example.com"
                                        />
                                        <InputError
                                            message={errors.buyer_email}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="buyer_phone">
                                            Téléphone (WhatsApp/SMS)
                                        </Label>
                                        <Input
                                            id="buyer_phone"
                                            name="buyer_phone"
                                            type="tel"
                                            placeholder="+221773698046"
                                            required
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Formats acceptés : +221773698046,
                                            00221773698046 ou 773698046 (sans
                                            espaces).
                                        </p>
                                        <InputError
                                            message={errors.buyer_phone}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <InputError message={errors.payment} />

                            <div className="flex items-center justify-between rounded-lg border p-4">
                                <span className="font-medium">Total</span>
                                <span className="text-lg font-semibold">
                                    {formatCurrency(total)}
                                </span>
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={
                                    processing || selectedItems.length === 0
                                }
                            >
                                Payer avec Wave
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
