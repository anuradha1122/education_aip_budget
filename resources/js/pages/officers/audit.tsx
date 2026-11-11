import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Reference = {
    id: number;
    code: string;
    name: string;
};

type OfficerValues = {
    office_id: number;
    designation_id: number;
    name: string;
    is_active: boolean;
};

type AuditEvent = {
    id: number;
    officer_id: number;
    action: string;
    old_values: OfficerValues | null;
    new_values: OfficerValues;
    created_at: string;
    actor_name: string | null;
};

type Props = {
    events: AuditEvent[];
    offices: Reference[];
    designations: Reference[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Officers', href: '/officers' },
    { title: 'Audit history', href: '/officers/audit' },
];

function referenceLabel(id: number, references: Reference[]): string {
    const reference = references.find((item) => item.id === Number(id));

    return reference
        ? `${reference.code} — ${reference.name} (ID: ${id})`
        : `ID: ${id} (name unavailable)`;
}

function Values({
    values,
    offices,
    designations,
}: {
    values: OfficerValues;
    offices: Reference[];
    designations: Reference[];
}) {
    return (
        <div className="rounded-md border p-3">
            <p>
                <span className="font-semibold">Name:</span> {values.name}
            </p>

            <p>
                <span className="font-semibold">Office:</span>{' '}
                {referenceLabel(values.office_id, offices)}
            </p>

            <p>
                <span className="font-semibold">Designation:</span>{' '}
                {referenceLabel(values.designation_id, designations)}
            </p>

            <p>
                <span className="font-semibold">Status:</span>{' '}
                {values.is_active ? 'Active' : 'Inactive'}
            </p>
        </div>
    );
}

export default function OfficerAudit({
    events,
    offices,
    designations,
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Officer audit history" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Officer audit history
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Officer changes recorded by the system. Office and
                        designation names shown are their current names.
                    </p>
                </div>

                <div>
                    <Link href="/officers" className="underline">
                        Back to officers
                    </Link>
                </div>

                {events.length === 0 ? (
                    <p>No officer audit events found.</p>
                ) : (
                    events.map((event) => (
                        <section
                            key={event.id}
                            className="flex flex-col gap-3 rounded-lg border p-4"
                        >
                            <p>
                                <span className="font-semibold">
                                    Officer ID:
                                </span>{' '}
                                {event.officer_id}
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

                                    <Values
                                        values={event.old_values}
                                        offices={offices}
                                        designations={designations}
                                    />
                                </div>
                            )}

                            <div>
                                <h2 className="mb-2 font-semibold">
                                    New values
                                </h2>

                                <Values
                                    values={event.new_values}
                                    offices={offices}
                                    designations={designations}
                                />
                            </div>
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}