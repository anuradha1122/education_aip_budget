import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type DesignationValues = {
    code: string;
    name: string;
    is_active: boolean;
};

type AuditEvent = {
    id: number;
    action: string;
    old_values: DesignationValues | null;
    new_values: DesignationValues;
    created_at: string;
    designation_code: string;
    designation_name: string;
    actor_name: string | null;
};

type Props = {
    events: AuditEvent[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Designations', href: '/designations' },
    { title: 'Audit history', href: '/designations/audit' },
];

function Values({ values }: { values: DesignationValues }) {
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

export default function DesignationAudit({ events }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Designation audit history" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Designation audit history
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Designation changes recorded by the system.
                    </p>
                </div>

                <div>
                    <Link href="/designations" className="underline">
                        Back to designations
                    </Link>
                </div>

                {events.length === 0 ? (
                    <p>No designation audit events found.</p>
                ) : (
                    events.map((event) => (
                        <section
                            key={event.id}
                            className="flex flex-col gap-3 rounded-lg border p-4"
                        >
                            <p>
                                <span className="font-semibold">
                                    Designation:
                                </span>{' '}
                                {event.designation_code} —{' '}
                                {event.designation_name}
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