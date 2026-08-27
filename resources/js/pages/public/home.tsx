import { Head, Link } from '@inertiajs/react';
import { CalendarDays, MapPin, Ticket as TicketIcon } from 'lucide-react';
import type { ReactElement } from 'react';
import { Badge } from '@/components/ui/badge';
import { buttonVariants } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { formatCurrency, formatShortDate } from '@/lib/format';
import { show } from '@/routes/events';

type EventCard = {
    id: number;
    slug: string;
    title: string;
    date: string;
    venue: string;
    city: string | null;
    cover_image: string | null;
    price_from: number | null;
};

// A distinct, deterministic gradient per event id, used as the cover
// placeholder when the organizer hasn't uploaded an image yet.
const COVER_GRADIENTS = [
    'from-orange-500/50 to-rose-600/10',
    'from-amber-400/50 to-orange-700/10',
    'from-pink-500/50 to-fuchsia-700/10',
    'from-sky-500/50 to-indigo-700/10',
    'from-emerald-500/50 to-teal-700/10',
    'from-violet-500/50 to-purple-700/10',
];

function gradientFor(id: number): string {
    return COVER_GRADIENTS[id % COVER_GRADIENTS.length];
}

export default function PublicHome({ events }: { events: EventCard[] }) {
    return (
        <>
            <Head title="Accueil" />

            <div className="space-y-12">
                <section className="relative overflow-hidden rounded-3xl bg-linear-to-br from-primary/90 via-primary/50 to-background px-6 py-16 sm:px-12 sm:py-24">
                    <div
                        className="pointer-events-none absolute inset-0 opacity-25"
                        style={{
                            backgroundImage:
                                'radial-gradient(circle, white 1px, transparent 1px)',
                            backgroundSize: '22px 22px',
                        }}
                    />
                    <TicketIcon
                        className="pointer-events-none absolute -right-10 -bottom-10 size-64 rotate-12 text-white/10"
                        strokeWidth={1}
                    />

                    <div className="relative mx-auto max-w-2xl space-y-5 text-center">
                        <Badge className="border-white/20 bg-white/10 text-white backdrop-blur">
                            La billetterie du Sénégal
                        </Badge>
                        <h1 className="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">
                            Vos événements au Sénégal
                        </h1>
                        <p className="text-lg text-white/80">
                            Concerts, festivals, spectacles et rencontres près
                            de chez vous — trouvez votre prochain événement et
                            achetez vos billets en toute sécurité.
                        </p>
                    </div>
                </section>

                <section className="space-y-6">
                    <div className="flex items-end justify-between gap-4">
                        <div className="space-y-1">
                            <h2 className="text-2xl font-bold tracking-tight">
                                Événements à venir
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {events.length} événement
                                {events.length > 1 ? 's' : ''} disponible
                                {events.length > 1 ? 's' : ''}
                            </p>
                        </div>
                    </div>

                    {events.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-border/60 py-20 text-center">
                            <TicketIcon className="mx-auto mb-3 size-8 text-muted-foreground" />
                            <p className="text-muted-foreground">
                                Aucun événement à venir pour le moment.
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {events.map((event) => (
                                <EventCardItem key={event.id} event={event} />
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function EventCardItem({ event }: { event: EventCard }): ReactElement {
    return (
        <div className="group relative flex flex-col overflow-hidden rounded-2xl border border-border/60 bg-card shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:shadow-primary/10">
            <Link href={show(event.slug)} className="contents">
                <div className="relative aspect-16/10 w-full overflow-hidden bg-muted">
                    {event.cover_image ? (
                        <img
                            src={`/storage/${event.cover_image}`}
                            alt={event.title}
                            className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                    ) : (
                        <div
                            className={`flex size-full items-center justify-center bg-linear-to-br ${gradientFor(event.id)}`}
                        >
                            <TicketIcon className="size-10 text-white/70" />
                        </div>
                    )}

                    {event.city && (
                        <span className="absolute top-3 left-3 rounded-full bg-background/90 px-2.5 py-1 text-xs font-semibold backdrop-blur">
                            {event.city}
                        </span>
                    )}
                </div>

                <div className="flex flex-1 flex-col gap-2 p-4">
                    <h3 className="line-clamp-2 font-bold tracking-tight">
                        {event.title}
                    </h3>

                    <div className="flex items-center gap-1.5 text-sm text-muted-foreground">
                        <CalendarDays className="size-4 shrink-0" />
                        <span className="line-clamp-1 capitalize">
                            {formatShortDate(event.date)}
                        </span>
                    </div>

                    <div className="flex items-center gap-1.5 text-sm text-muted-foreground">
                        <MapPin className="size-4 shrink-0" />
                        <span className="line-clamp-1">{event.venue}</span>
                    </div>

                    <div className="mt-auto flex items-end justify-between gap-3 pt-3">
                        <div>
                            {event.price_from !== null ? (
                                <>
                                    <div className="text-xs text-muted-foreground">
                                        À partir de
                                    </div>
                                    <div className="text-lg font-bold text-primary">
                                        {formatCurrency(event.price_from)}
                                    </div>
                                </>
                            ) : (
                                <span className="text-sm text-muted-foreground">
                                    Prix à venir
                                </span>
                            )}
                        </div>

                        <span
                            className={buttonVariants({
                                size: 'sm',
                                className: 'pointer-events-none',
                            })}
                        >
                            Acheter
                        </span>
                    </div>
                </div>
            </Link>
        </div>
    );
}

PublicHome.layout = (page: ReactElement) => (
    <PublicLayout wide>{page}</PublicLayout>
);
