import { Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/Admin/EventController';
import AdminEventForm from '@/components/admin-event-form';
import Heading from '@/components/heading';
import { index } from '@/routes/admin/events';

type Organizer = {
    id: number;
    name: string;
    email: string;
};

export default function EventsCreate({
    organizers,
    statuses,
}: {
    organizers: Organizer[];
    statuses: string[];
}) {
    return (
        <>
            <Head title="Créer un événement" />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title="Créer un événement"
                    description="Renseignez les informations de l'événement et ses types de billets"
                />

                <AdminEventForm
                    formOptions={EventController.store.form()}
                    organizers={organizers}
                    statuses={statuses}
                    submitLabel="Créer l'événement"
                />
            </div>
        </>
    );
}

EventsCreate.layout = {
    breadcrumbs: [
        { title: 'Événements', href: index() },
        { title: 'Créer', href: '#' },
    ],
};
