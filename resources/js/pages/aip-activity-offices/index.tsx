import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Activity = {
    id: number;
    activity_number: string;
    description: string;
    year: number;
};

type Office = {
    id: number;
    code: string;
    name: string;
    is_active: boolean | number;
};

type ActivityOfficeLink = {
    id: number;
    activity_number: string;
    description: string;
    year: number;
    office_code: string;
    office_name: string;
};

type PageLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    activities: Activity[];
    offices: Office[];
    links: {
        data: ActivityOfficeLink[];
        links: PageLink[];
    };
};

export default function AipActivityOffices({
    activities,
    offices,
    links,
}: Props) {
    const { data, setData, post, processing, errors, recentlySuccessful, reset } =
        useForm({
            aip_activity_id: '',
            office_id: '',
        });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post('/aip-activity-offices', {
            onSuccess: () => reset(),
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'AIP activities', href: '/aip-activities' },
                { title: 'Activity offices', href: '/aip-activity-offices' },
            ]}
        >
            <Head title="AIP activity offices" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        AIP activity offices
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Link a common activity to the offices that use it.
                    </p>

                    <Link
                        href="/aip-activities"
                        className="mt-3 inline-block underline"
                    >
                        Back to AIP activities
                    </Link>
                </div>

                <form
                    onSubmit={submit}
                    className="flex max-w-3xl flex-col gap-4 rounded border p-4"
                >
                    <h2 className="text-lg font-semibold">
                        Link an office
                    </h2>

                    <label>
                        Activity
                        <select
                            required
                            value={data.aip_activity_id}
                            onChange={(event) =>
                                setData('aip_activity_id', event.target.value)
                            }
                            className="mt-1 block w-full rounded border bg-background p-2"
                        >
                            <option value="">Select an activity</option>

                            {activities.map((activity) => (
                                <option key={activity.id} value={activity.id}>
                                    {activity.year} — {activity.activity_number}
                                    {' — '}
                                    {activity.description}
                                </option>
                            ))}
                        </select>
                    </label>

                    {activities.length === 0 && (
                        <p className="text-sm">
                            No activities in open financial years are available.
                        </p>
                    )}

                    {errors.aip_activity_id && (
                        <p className="text-sm text-red-600">
                            {errors.aip_activity_id}
                        </p>
                    )}

                    <label>
                        Office
                        <select
                            required
                            value={data.office_id}
                            onChange={(event) =>
                                setData('office_id', event.target.value)
                            }
                            className="mt-1 block w-full rounded border bg-background p-2"
                        >
                            <option value="">Select an office</option>

                            {offices.map((office) => (
                                <option key={office.id} value={office.id}>
                                    {office.code} — {office.name}
                                    {Number(office.is_active) === 0
                                        ? ' (inactive)'
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </label>

                    {errors.office_id && (
                        <p className="text-sm text-red-600">
                            {errors.office_id}
                        </p>
                    )}

                    <div>
                        <button
                            type="submit"
                            disabled={
                                processing ||
                                activities.length === 0 ||
                                offices.length === 0
                            }
                            className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Link office'}
                        </button>
                    </div>

                    {recentlySuccessful && (
                        <p role="status" className="text-sm">
                            Office linked successfully.
                        </p>
                    )}
                </form>

                <section>
                    <h2 className="text-lg font-semibold">Existing links</h2>

                    {links.data.length === 0 ? (
                        <p className="mt-3">No offices have been linked yet.</p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-2">Year</th>
                                        <th className="p-2">Activity</th>
                                        <th className="p-2">Description</th>
                                        <th className="p-2">Office</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {links.data.map((link) => (
                                        <tr className="border-b" key={link.id}>
                                            <td className="p-2">{link.year}</td>
                                            <td className="p-2">
                                                {link.activity_number}
                                            </td>
                                            <td className="p-2">
                                                {link.description}
                                            </td>
                                            <td className="p-2">
                                                {link.office_code} —{' '}
                                                {link.office_name}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <nav
                        aria-label="Activity office pages"
                        className="mt-4 flex flex-wrap gap-2"
                    >
                        {links.links.map((link, index) => {
                            const label = link.label
                                .replace(/&laquo;|&raquo;/g, '')
                                .trim();

                            return link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    className="rounded border px-3 py-2 text-sm"
                                >
                                    {label}
                                </Link>
                            ) : (
                                <span
                                    key={index}
                                    className="rounded border px-3 py-2 text-sm opacity-50"
                                >
                                    {label}
                                </span>
                            );
                        })}
                    </nav>
                </section>
            </div>
        </AppLayout>
    );
}