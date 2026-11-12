import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type ProgrammeTypeValues = {
    code: string;
    name: string;
    is_active: boolean;
};

type AuditEvent = {
    id: number;
    programme_type_id: number;
    action: string;
    old_values: ProgrammeTypeValues | null;
    new_values: ProgrammeTypeValues;
    created_at: string;
    actor_name: string | null;
};

type Props = {
    events: AuditEvent[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Programme types', href: '/programme-types' },
    { title: 'Audit history', href: '/programme-types/audit' },
];

function Values({ values }: { values: ProgrammeTypeValues }) {
    return (
        <div className="rounded-md border p-3">
            <p>
                <span className="font-semibold">Code:</span> {values.code}
            </p>

            <p>
                <span className="font-semibold">Name:</span> {values.name}
            </p>

            <p>
                <span className="font-semibold">Status:</span>{' '}
                {values.is_active ? 'Active' : 'Inactive'}
            </p>
        </div>
    );
}

export default function ProgrammeTypeAudit({ events }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Programme type audit history" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Programme type audit history
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Programme type changes recorded by the system.
                    </p>
                </div>

                <div>
                    <Link href="/programme-types" className="underline">
                        Back to programme types
                    </Link>
                </div>

                {events.length === 0 ? (
                    <p>No programme type audit events found.</p>
                ) : (
                    events.map((event) => (
                        <section
                            key={event.id}
                            className="flex flex-col gap-3 rounded-lg border p-4"
                        >
                            <p>
                                <span className="font-semibold">
                                    Programme type ID:
                                </span>{' '}
                                {event.programme_type_id}
                            </p>

                            <p>
                                <span className="font-semibold">Action:</span>{' '}
                                {event.action}
                            </p>

                            <p>
                                <span className="font-semibold">User:</span>{' '}
                                {event.actor_name ?? 'Not available'}
                            </p>

                            <p>
                                <span className="font-semibold">
                                    Recorded time:
                                </span>{' '}
                                {event.created_at}
                            </p>

                            {event.old_values && (
                                <div>
                                    <h2 className="mb-2 font-semibold">
                                        Previous values
                                    </h2>

                                    <Values values={event.old_values} />
                                </div>
                            )}

                            <div>
                                <h2 className="mb-2 font-semibold">
                                    New values
                                </h2>

                                <Values values={event.new_values} />
                            </div>
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}