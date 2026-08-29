import { Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import AdminEventForm from '@/components/admin-event-form';
import Heading from '@/components/heading';
import { index } from '@/routes/admin/events';

type Organizer = {
    id: number;
    name: string;
    email: string;
    role: 'organizer' | 'admin';
};

type EventDetails = {
    id: number;
    organizer_id: number;
    title: string;
    description: string | null;
    date: string;
    venue: string;
    city: string | null;
    capacity: number;
    cover_image: string | null;
    status: string;
    ticket_types: { id: number; name: string; price: number }[];
};

export default function EventsEdit({
    event,
    organizers,
    statuses,
}: {
    event: EventDetails;
    organizers: Organizer[];
    statuses: string[];
}) {
    return (
        <>
            <Head title={`Modifier ${event.title}`} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title="Modifier l'événement"
                    description="Mettez à jour les informations de l'événement et ses types de billets"
                />

                <AdminEventForm
                    formOptions={EventController.update.form(event.id)}
                    organizers={organizers}
                    statuses={statuses}
                    submitLabel="Enregistrer les modifications"
                    defaults={{
                        organizer_id: event.organizer_id,
                        title: event.title,
                        description: event.description ?? undefined,
                        date: event.date,
                        venue: event.venue,
                        city: event.city ?? undefined,
                        capacity: event.capacity,
                        status: event.status,
                        cover_image: event.cover_image,
                        ticket_types: event.ticket_types.map((ticketType) => ({
                            id: ticketType.id,
                            name: ticketType.name,
                            price: String(ticketType.price),
                        })),
                    }}
                />
            </div>
        </>
    );
}

EventsEdit.layout = {
    breadcrumbs: [{ title: 'Événements', href: index() }],
};
