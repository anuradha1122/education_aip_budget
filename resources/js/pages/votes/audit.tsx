import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type VoteValues = {
    code: string;
    description: string;
    is_active: boolean;
};

type AuditEvent = {
    id: number;
    action: string;
    old_values: VoteValues | null;
    new_values: VoteValues | null;
    created_at: string;
    vote_code: string;
    vote_description: string;
    actor_name: string | null;
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
    { title: 'Votes', href: '/votes' },
    { title: 'Audit history', href: '/vote-audit' },
];

function Values({ values }: { values: VoteValues | null }) {
    if (!values) {
        return <p className="text-sm">Not applicable</p>;
    }

    return (
        <dl className="space-y-1 text-sm">
            <div>
                <dt className="inline font-medium">Code: </dt>
                <dd className="inline">{values.code}</dd>
            </div>

            <div>
                <dt className="inline font-medium">Description: </dt>
                <dd className="inline">{values.description}</dd>
            </div>

            <div>
                <dt className="inline font-medium">Status: </dt>
                <dd className="inline">
                    {values.is_active ? 'Active' : 'Inactive'}
                </dd>
            </div>
        </dl>
    );
}

export default function VoteAudit({ events }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vote audit history" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Vote audit history
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Vote changes recorded by the system.
                    </p>

                    <Link href="/votes" className="mt-3 inline-block underline">
                        Back to votes
                    </Link>
                </div>

                {events.data.length === 0 ? (
                    <p className="rounded-lg border p-4">
                        No vote audit events have been recorded yet.
                    </p>
                ) : (
                    events.data.map((event) => (
                        <section
                            key={event.id}
                            className="rounded-lg border p-4"
                        >
                            <div className="grid gap-2 md:grid-cols-2">
                                <p>
                                    <strong>Vote: </strong>
                                    {event.vote_code} — {event.vote_description}
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

                <nav className="flex items-center gap-3" aria-label="Pagination">
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