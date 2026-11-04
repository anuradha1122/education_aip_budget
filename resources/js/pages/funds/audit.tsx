import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type AuditEvent = {
    id: number;
    fund_code: string;
    fund_name: string;
    actor_name: string | null;
    action: string;
    old_values: string | null;
    new_values: string;
    created_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    events: {
        data: AuditEvent[];
        links: PaginationLink[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Funds', href: '/funds' },
    { title: 'Audit history', href: '/fund-audit' },
];

function displayValues(value: string | null): string {
    if (value === null) {
        return 'Not applicable';
    }

    try {
        return JSON.stringify(JSON.parse(value), null, 2);
    } catch {
        return value;
    }
}

export default function FundAudit({ events }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Fund audit history" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Fund audit history
                    </h1>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Fund changes recorded by the system.
                    </p>

                    <Link
                        href="/funds"
                        className="mt-3 inline-block text-sm underline"
                    >
                        Back to funds
                    </Link>
                </div>

                {events.data.length === 0 ? (
                    <p className="rounded-lg border p-4">
                        No fund audit events have been recorded yet.
                    </p>
                ) : (
                    <div className="flex flex-col gap-4">
                        {events.data.map((event) => (
                            <article
                                key={event.id}
                                className="rounded-lg border p-4"
                            >
                                <div className="grid gap-2 text-sm md:grid-cols-2">
                                    <p>
                                        <strong>Fund:</strong>{' '}
                                        {event.fund_code} — {event.fund_name}
                                    </p>

                                    <p>
                                        <strong>Action:</strong> {event.action}
                                    </p>

                                    <p>
                                        <strong>User:</strong>{' '}
                                        {event.actor_name ?? 'Unavailable'}
                                    </p>

                                    <p>
                                        <strong>Recorded time:</strong>{' '}
                                        {event.created_at}
                                    </p>
                                </div>

                                <div className="mt-4 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <h2 className="mb-2 font-medium">
                                            Previous values
                                        </h2>

                                        <pre className="overflow-x-auto rounded-md border p-3 text-xs">
                                            {displayValues(event.old_values)}
                                        </pre>
                                    </div>

                                    <div>
                                        <h2 className="mb-2 font-medium">
                                            New values
                                        </h2>

                                        <pre className="overflow-x-auto rounded-md border p-3 text-xs">
                                            {displayValues(event.new_values)}
                                        </pre>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}

                <nav
                    aria-label="Fund audit history pages"
                    className="flex flex-wrap gap-2"
                >
                    {events.links.map((link, index) => {
                        const label = link.label
                            .replace(/&laquo;|&raquo;/g, '')
                            .trim();

                        return link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                aria-current={link.active ? 'page' : undefined}
                                className={`rounded-md border px-3 py-2 text-sm ${
                                    link.active ? 'font-bold' : ''
                                }`}
                            >
                                {label}
                            </Link>
                        ) : (
                            <span
                                key={index}
                                className="rounded-md border px-3 py-2 text-sm opacity-50"
                            >
                                {label}
                            </span>
                        );
                    })}
                </nav>
            </div>
        </AppLayout>
    );
}
