import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type YearValues = {
    year: number;
    start_date: string;
    end_date: string;
    is_closed: boolean;
};

type AuditEvent = {
    id: number;
    year: number;
    action: string;
    actor_name: string | null;
    created_at: string;
    old_values: YearValues | null;
    new_values: YearValues | null;
};

type PaginatedEvents = {
    data: AuditEvent[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    events: PaginatedEvents;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Financial years', href: '/financial-years' },
    { title: 'Audit history', href: '/financial-year-audit' },
];

function Values({ values }: { values: YearValues | null }) {
    if (!values) {
        return <p className="text-sm">Not applicable</p>;
    }

    return (
        <dl className="space-y-1 text-sm">
            <div>
                <dt className="inline font-medium">Year: </dt>
                <dd className="inline">{values.year}</dd>
            </div>

            <div>
                <dt className="inline font-medium">Start date: </dt>
                <dd className="inline">{values.start_date}</dd>
            </div>

            <div>
                <dt className="inline font-medium">End date: </dt>
                <dd className="inline">{values.end_date}</dd>
            </div>

            <div>
                <dt className="inline font-medium">Status: </dt>
                <dd className="inline">
                    {values.is_closed ? 'Closed' : 'Open'}
                </dd>
            </div>
        </dl>
    );
}

export default function FinancialYearAudit({ events }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Financial year audit history" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Financial year audit history
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Financial year changes recorded by the system.
                    </p>

                    <Link
                        href="/financial-years"
                        className="mt-3 inline-block underline"
                    >
                        Back to financial years
                    </Link>
                </div>

                {events.data.length === 0 ? (
                    <p className="rounded-lg border p-4">
                        No financial year audit events have been recorded yet.
                    </p>
                ) : (
                    events.data.map((event) => (
                        <section
                            key={event.id}
                            className="rounded-lg border p-4"
                        >
                            <div className="grid gap-2 md:grid-cols-2">
                                <p>
                                    <strong>Financial year: </strong>
                                    {event.year}
                                </p>

                                <p>
                                    <strong>Action: </strong>
                                    {event.action}
                                </p>

                                <p>
                                    <strong>User: </strong>
                                    {event.actor_name ?? 'Not available'}
                                </p>

                                <p>
                                    <strong>Recorded time: </strong>
                                    {event.created_at}
                                </p>
                            </div>

                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <div className="rounded-md border p-3">
                                    <h2 className="mb-2 font-semibold">
                                        Previous values
                                    </h2>

                                    <Values values={event.old_values} />
                                </div>

                                <div className="rounded-md border p-3">
                                    <h2 className="mb-2 font-semibold">
                                        New values
                                    </h2>

                                    <Values values={event.new_values} />
                                </div>
                            </div>
                        </section>
                    ))
                )}

                <nav
                    className="flex items-center gap-3"
                    aria-label="Pagination"
                >
                    {events.prev_page_url ? (
                        <Link
                            href={events.prev_page_url}
                            className="rounded-md border px-3 py-2"
                        >
                            Previous
                        </Link>
                    ) : (
                        <span className="rounded-md border px-3 py-2 opacity-50">
                            Previous
                        </span>
                    )}

                    <span className="text-sm">
                        Page {events.current_page} of {events.last_page}
                    </span>

                    {events.next_page_url ? (
                        <Link
                            href={events.next_page_url}
                            className="rounded-md border px-3 py-2"
                        >
                            Next
                        </Link>
                    ) : (
                        <span className="rounded-md border px-3 py-2 opacity-50">
                            Next
                        </span>
                    )}
                </nav>
            </div>
        </AppLayout>
    );
}