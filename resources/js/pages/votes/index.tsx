import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Vote = {
    id: number;
    code: string;
    description: string;
    is_active: boolean;
};

type Props = {
    votes: Vote[];
    search: string;
    editingVote: Vote | null;
};

type VoteForm = {
    code: string;
    description: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Votes', href: '/votes' },
];

export default function VoteIndex(props: Props) {
    return (
        <VotePage
            key={props.editingVote?.id ?? 'new'}
            {...props}
        />
    );
}

function VotePage({ votes, search, editingVote }: Props) {
    const [searchTerm, setSearchTerm] = useState(search);

    const { data, setData, post, put, processing, errors, reset } =
        useForm<VoteForm>({
            code: editingVote?.code ?? '',
            description: editingVote?.description ?? '',
            is_active: editingVote?.is_active ?? true,
        });

    function submitVote(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingVote) {
            put(`/votes/${editingVote.id}`);
        } else {
            post('/votes', {
                onSuccess: () => reset(),
            });
        }
    }

    function searchVotes(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/votes',
            { search: searchTerm },
            { preserveState: false },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Votes" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Vote management
                    </h1>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Create, update and deactivate votes.
                    </p>

                    <div className="mt-3 flex flex-wrap gap-x-6 gap-y-2">
                        <Link href="/vote-audit" className="underline">
                            View vote audit history
                        </Link>

                        <a href="/votes/export/excel" className="underline">
                            Download vote list (Excel)
                        </a>

                        <a href="/votes/export/pdf" className="underline">
                            Download vote list (PDF)
                        </a>

                        <a href="/votes/import/template" className="underline">
                            Download vote import template
                        </a>

                        <Link href="/votes/import" className="underline">
                            Validate vote import
                        </Link>
                    </div>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        {editingVote ? 'Edit vote' : 'Add vote'}
                    </h2>

                    <form
                        onSubmit={submitVote}
                        className="grid gap-4 md:grid-cols-2"
                    >
                        <div>
                            <label
                                htmlFor="code"
                                className="mb-1 block text-sm font-medium"
                            >
                                Vote code
                            </label>

                            <input
                                id="code"
                                value={data.code}
                                onChange={(event) =>
                                    setData('code', event.target.value)
                                }
                                maxLength={50}
                                required
                                className="w-full rounded-md border bg-background p-2"
                            />

                            {errors.code && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.code}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="description"
                                className="mb-1 block text-sm font-medium"
                            >
                                Vote description
                            </label>

                            <input
                                id="description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                maxLength={255}
                                required
                                className="w-full rounded-md border bg-background p-2"
                            />

                            {errors.description && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.description}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="is_active"
                                className="mb-1 block text-sm font-medium"
                            >
                                Status
                            </label>

                            <select
                                id="is_active"
                                value={data.is_active ? 'active' : 'inactive'}
                                onChange={(event) =>
                                    setData(
                                        'is_active',
                                        event.target.value === 'active',
                                    )
                                }
                                className="w-full rounded-md border bg-background p-2"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>

                            {errors.is_active && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.is_active}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center gap-3 md:col-span-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                            >
                                {editingVote ? 'Save changes' : 'Create vote'}
                            </button>

                            {editingVote && (
                                <Link href="/votes" className="text-sm underline">
                                    Cancel editing
                                </Link>
                            )}
                        </div>
                    </form>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        Votes
                    </h2>

                    <form
                        onSubmit={searchVotes}
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        <label htmlFor="search" className="sr-only">
                            Search votes
                        </label>

                        <input
                            id="search"
                            value={searchTerm}
                            onChange={(event) =>
                                setSearchTerm(event.target.value)
                            }
                            placeholder="Search by code or description"
                            className="min-w-48 flex-1 rounded-md border bg-background p-2"
                        />

                        <button
                            type="submit"
                            className="rounded-md border px-4 py-2"
                        >
                            Search
                        </button>

                        <Link
                            href="/votes"
                            className="rounded-md border px-4 py-2"
                        >
                            Clear
                        </Link>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-2">Code</th>
                                    <th className="p-2">Description</th>
                                    <th className="p-2">Status</th>
                                    <th className="p-2">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                {votes.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="p-2">
                                            No votes found.
                                        </td>
                                    </tr>
                                ) : (
                                    votes.map((vote) => (
                                        <tr key={vote.id} className="border-b">
                                            <td className="p-2">
                                                {vote.code}
                                            </td>
                                            <td className="p-2">
                                                {vote.description}
                                            </td>
                                            <td className="p-2">
                                                {vote.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </td>
                                            <td className="p-2">
                                                <Link
                                                    href={`/votes/${vote.id}/edit`}
                                                    className="underline"
                                                >
                                                    Edit
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}