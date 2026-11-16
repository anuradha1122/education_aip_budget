import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Activity = {
    id: number;
    year: number;
    activity_number: string;
    description: string;
};

type Vote = {
    id: number;
    code: string;
    description: string;
    is_active: boolean | number;
};

type Fund = {
    id: number;
    code: string;
    name: string;
    is_active: boolean | number;
};

type Entry = {
    id: number;
    year: number;
    activity_number: string;
    activity_description: string;
    vote_code: string;
    fund_code: string;
    fund_name: string;
};

type PageLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    activities: Activity[];
    votes: Vote[];
    funds: Fund[];
    entries: {
        data: Entry[];
        links: PageLink[];
    };
};

export default function AipFundingEntries({
    activities,
    votes,
    funds,
    entries,
}: Props) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        recentlySuccessful,
        reset,
    } = useForm({
        aip_activity_id: '',
        vote_id: '',
        fund_id: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post('/aip-funding-entries', {
            onSuccess: () => reset(),
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'AIP activities', href: '/aip-activities' },
                { title: 'Votes and funds', href: '/aip-funding-entries' },
            ]}
        >
            <Head title="AIP activity votes and funds" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        AIP activity votes and funds
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Assign the vote and fund recorded for an activity.
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
                    className="flex w-full max-w-2xl flex-col gap-4 rounded border p-4"
                >
                    <h2 className="text-lg font-semibold">Add funding entry</h2>

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

                    {errors.aip_activity_id && (
                        <p className="text-sm text-red-600">
                            {errors.aip_activity_id}
                        </p>
                    )}

                    <label>
                        Vote
                        <select
                            required
                            value={data.vote_id}
                            onChange={(event) =>
                                setData('vote_id', event.target.value)
                            }
                            className="mt-1 block w-full rounded border bg-background p-2"
                        >
                            <option value="">Select a vote</option>

                            {votes.map((vote) => (
                                <option key={vote.id} value={vote.id}>
                                    {vote.code} — {vote.description}
                                    {Number(vote.is_active) === 0
                                        ? ' (inactive)'
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </label>

                    {errors.vote_id && (
                        <p className="text-sm text-red-600">
                            {errors.vote_id}
                        </p>
                    )}

                    <label>
                        Fund
                        <select
                            required
                            value={data.fund_id}
                            onChange={(event) =>
                                setData('fund_id', event.target.value)
                            }
                            className="mt-1 block w-full rounded border bg-background p-2"
                        >
                            <option value="">Select a fund</option>

                            {funds.map((fund) => (
                                <option key={fund.id} value={fund.id}>
                                    {fund.code} — {fund.name}
                                    {Number(fund.is_active) === 0
                                        ? ' (inactive)'
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </label>

                    {errors.fund_id && (
                        <p className="text-sm text-red-600">
                            {errors.fund_id}
                        </p>
                    )}

                    <p className="text-sm text-muted-foreground">
                        Review the existing entries before saving.
                    </p>

                    <div>
                        <button
                            type="submit"
                            disabled={
                                processing ||
                                activities.length === 0 ||
                                votes.length === 0 ||
                                funds.length === 0
                            }
                            className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Save entry'}
                        </button>
                    </div>

                    {recentlySuccessful && (
                        <p role="status" className="text-sm">
                            Funding entry saved.
                        </p>
                    )}
                </form>

                <section>
                    <h2 className="text-lg font-semibold">Existing entries</h2>

                    {entries.data.length === 0 ? (
                        <p className="mt-3">No funding entries found.</p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-2">Year</th>
                                        <th className="p-2">Activity number</th>
                                        <th className="p-2">Activity</th>
                                        <th className="p-2">Vote</th>
                                        <th className="p-2">Fund</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {entries.data.map((entry) => (
                                        <tr className="border-b" key={entry.id}>
                                            <td className="p-2">{entry.year}</td>
                                            <td className="p-2">
                                                {entry.activity_number}
                                            </td>
                                            <td className="p-2">
                                                {entry.activity_description}
                                            </td>
                                            <td className="p-2">
                                                {entry.vote_code}
                                            </td>
                                            <td className="p-2">
                                                {entry.fund_code} —{' '}
                                                {entry.fund_name}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <nav
                        aria-label="Funding entry pages"
                        className="mt-4 flex flex-wrap gap-2"
                    >
                        {entries.links.map((link, index) => {
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