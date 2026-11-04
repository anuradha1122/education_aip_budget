import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Fund = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

type Props = {
    funds: Fund[];
    search: string;
    editingFund: Fund | null;
};

type FundForm = {
    code: string;
    name: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Funds', href: '/funds' },
];

export default function FundIndex(props: Props) {
    return <FundPage key={props.editingFund?.id ?? 'new'} {...props} />;
}

function FundPage({ funds, search, editingFund }: Props) {
    const [searchTerm, setSearchTerm] = useState(search);

    const { data, setData, post, put, processing, errors, reset } =
        useForm<FundForm>({
            code: editingFund?.code ?? '',
            name: editingFund?.name ?? '',
            is_active: editingFund?.is_active ?? true,
        });

    function submitFund(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingFund) {
            put(`/funds/${editingFund.id}`);
        } else {
            post('/funds', {
                onSuccess: () => reset(),
            });
        }
    }

    function searchFunds(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/funds',
            { search: searchTerm },
            { preserveState: false },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Funds" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Fund management
                    </h1>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Create, update and deactivate funds.
                    </p>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        {editingFund ? 'Edit fund' : 'Add fund'}
                    </h2>

                    <form
                        onSubmit={submitFund}
                        className="grid gap-4 md:grid-cols-2"
                    >
                        <div>
                            <label
                                htmlFor="code"
                                className="mb-1 block text-sm font-medium"
                            >
                                Fund code
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
                                htmlFor="name"
                                className="mb-1 block text-sm font-medium"
                            >
                                Fund name
                            </label>

                            <input
                                id="name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                maxLength={255}
                                required
                                className="w-full rounded-md border bg-background p-2"
                            />

                            {errors.name && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.name}
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
                                {editingFund ? 'Save changes' : 'Create fund'}
                            </button>

                            {editingFund && (
                                <Link href="/funds" className="text-sm underline">
                                    Cancel editing
                                </Link>
                            )}
                        </div>
                    </form>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">Funds</h2>

                    <form
                        onSubmit={searchFunds}
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        <label htmlFor="search" className="sr-only">
                            Search funds
                        </label>

                        <input
                            id="search"
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder="Search by code or name"
                            className="min-w-48 flex-1 rounded-md border bg-background p-2"
                        />

                        <button type="submit" className="rounded-md border px-4 py-2">
                            Search
                        </button>

                        <Link href="/funds" className="rounded-md border px-4 py-2">
                            Clear
                        </Link>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-2">Code</th>
                                    <th className="p-2">Name</th>
                                    <th className="p-2">Status</th>
                                    <th className="p-2">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                {funds.map((fund) => (
                                    <tr key={fund.id} className="border-b">
                                        <td className="p-2">{fund.code}</td>
                                        <td className="p-2">{fund.name}</td>
                                        <td className="p-2">
                                            {fund.is_active ? 'Active' : 'Inactive'}
                                        </td>
                                        <td className="p-2">
                                            <Link
                                                href={`/funds/${fund.id}/edit`}
                                                className="underline"
                                            >
                                                Edit
                                            </Link>
                                        </td>
                                    </tr>
                                ))}

                                {funds.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="p-2">
                                            No funds found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
