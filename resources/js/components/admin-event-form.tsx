import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { RouteFormDefinition } from '@/wayfinder';

type Organizer = {
    id: number;
    name: string;
    email: string;
};

type TicketTypeInput = {
    id?: number;
    name: string;
    price: string;
};

export type AdminEventFormDefaults = {
    organizer_id?: number;
    title?: string;
    description?: string;
    date?: string;
    venue?: string;
    city?: string;
    capacity?: number;
    status?: string;
    cover_image?: string | null;
    ticket_types?: TicketTypeInput[];
};

const STATUS_LABELS: Record<string, string> = {
    draft: 'Brouillon',
    published: 'Publié',
    cancelled: 'Annulé',
    ended: 'Terminé',
};

export default function AdminEventForm({
    formOptions,
    organizers,
    statuses,
    defaults = {},
    submitLabel,
}: {
    formOptions: RouteFormDefinition<'get' | 'post'>;
    organizers: Organizer[];
    statuses: string[];
    defaults?: AdminEventFormDefaults;
    submitLabel: string;
}) {
    const [ticketTypes, setTicketTypes] = useState<TicketTypeInput[]>(
        defaults.ticket_types?.length
            ? defaults.ticket_types
            : [{ name: '', price: '' }],
    );

    function addTicketType() {
        setTicketTypes((rows) => [...rows, { name: '', price: '' }]);
    }

    function removeTicketType(index: number) {
        setTicketTypes((rows) => rows.filter((_, i) => i !== index));
    }

    function updateTicketType(
        index: number,
        field: 'name' | 'price',
        value: string,
    ) {
        setTicketTypes((rows) =>
            rows.map((row, i) =>
                i === index ? { ...row, [field]: value } : row,
            ),
        );
    }

    return (
        <Form {...formOptions} className="space-y-8">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Titre</Label>
                            <Input
                                id="title"
                                name="title"
                                defaultValue={defaults.title}
                                required
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="organizer_id">Organisateur</Label>
                            <Select
                                name="organizer_id"
                                defaultValue={
                                    defaults.organizer_id
                                        ? String(defaults.organizer_id)
                                        : undefined
                                }
                            >
                                <SelectTrigger
                                    id="organizer_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Sélectionner un organisateur" />
                                </SelectTrigger>
                                <SelectContent>
                                    {organizers.map((organizer) => (
                                        <SelectItem
                                            key={organizer.id}
                                            value={String(organizer.id)}
                                        >
                                            {organizer.name} ({organizer.email})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.organizer_id} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            name="description"
                            defaultValue={defaults.description}
                            rows={4}
                            className={cn(
                                'flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground md:text-sm',
                                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                            )}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="date">Date et heure</Label>
                            <Input
                                id="date"
                                type="datetime-local"
                                name="date"
                                defaultValue={defaults.date}
                                required
                            />
                            <InputError message={errors.date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status">Statut</Label>
                            <Select
                                name="status"
                                defaultValue={defaults.status ?? statuses[0]}
                            >
                                <SelectTrigger id="status" className="w-full">
                                    <SelectValue placeholder="Sélectionner un statut" />
                                </SelectTrigger>
                                <SelectContent>
                                    {statuses.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {STATUS_LABELS[status] ?? status}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="venue">Lieu</Label>
                            <Input
                                id="venue"
                                name="venue"
                                defaultValue={defaults.venue}
                                required
                            />
                            <InputError message={errors.venue} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="city">Ville</Label>
                            <Input
                                id="city"
                                name="city"
                                defaultValue={defaults.city}
                            />
                            <InputError message={errors.city} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="capacity">Capacité</Label>
                            <Input
                                id="capacity"
                                type="number"
                                min={1}
                                name="capacity"
                                defaultValue={defaults.capacity}
                                required
                            />
                            <InputError message={errors.capacity} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="cover_image">Image de couverture</Label>
                        {defaults.cover_image && (
                            <img
                                src={`/storage/${defaults.cover_image}`}
                                alt="Image actuelle de l'événement"
                                className="h-32 w-auto rounded-md border object-cover"
                            />
                        )}
                        <Input
                            id="cover_image"
                            type="file"
                            name="cover_image"
                            accept="image/*"
                        />
                        <InputError message={errors.cover_image} />
                    </div>

                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <Label>Types de billets</Label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addTicketType}
                            >
                                Ajouter un type de billet
                            </Button>
                        </div>

                        <div className="space-y-4">
                            {ticketTypes.map((row, index) => (
                                <div
                                    key={row.id ?? `new-${index}`}
                                    className="grid gap-4 rounded-md border p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-start"
                                >
                                    {row.id && (
                                        <input
                                            type="hidden"
                                            name={`ticket_types[${index}][id]`}
                                            value={row.id}
                                        />
                                    )}

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`ticket_types_${index}_name`}
                                        >
                                            Nom
                                        </Label>
                                        <Input
                                            id={`ticket_types_${index}_name`}
                                            name={`ticket_types[${index}][name]`}
                                            value={row.name}
                                            onChange={(e) =>
                                                updateTicketType(
                                                    index,
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Standard, VIP..."
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `ticket_types.${index}.name`
                                                ]
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`ticket_types_${index}_price`}
                                        >
                                            Prix (FCFA)
                                        </Label>
                                        <Input
                                            id={`ticket_types_${index}_price`}
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            name={`ticket_types[${index}][price]`}
                                            value={row.price}
                                            onChange={(e) =>
                                                updateTicketType(
                                                    index,
                                                    'price',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `ticket_types.${index}.price`
                                                ]
                                            }
                                        />
                                    </div>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={ticketTypes.length === 1}
                                        onClick={() => removeTicketType(index)}
                                        className="sm:mt-6"
                                    >
                                        Retirer
                                    </Button>
                                </div>
                            ))}
                        </div>
                        <InputError message={errors.ticket_types} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>{submitLabel}</Button>
                    </div>
                </>
            )}
        </Form>
    );
}
