import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Office = {
    id: number;
    code: string;
    name: string;
    type: string;
    is_active: boolean;
};

type Props = {
    offices: Office[];
    search: string;
    editingOffice: Office | null;
};

type OfficeForm = {
    code: string;
    name: string;
    type: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Offices',
        href: '/offices',
    },
];

export default function OfficeIndex(props: Props) {
    return <OfficePage key={props.editingOffice?.id ?? 'new'} {...props} />;
}

function OfficePage({
    offices,
    search,
    editingOffice,
}: Props) {
    const [searchTerm, setSearchTerm] = useState(search);

    const { data, setData, post, put, processing, errors, reset } =
        useForm<OfficeForm>({
            code: editingOffice?.code ?? '',
            name: editingOffice?.name ?? '',
            type: editingOffice?.type ?? 'zonal',
            is_active: editingOffice?.is_active ?? true,
        });

    function submitOffice(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingOffice) {
            put(`/offices/${editingOffice.id}`);
        } else {
            post('/offices', {
                onSuccess: () => reset(),
            });
        }
    }

    function searchOffices(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/offices',
            { search: searchTerm },
            { preserveState: false },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Offices" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Office management</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Create, update and deactivate offices.
                    </p>
                    <Link href="/office-audit" className="mt-3 inline-block text-sm underline">
                        View office audit history
                    </Link>

                    <a
                        href="/offices/export/excel"
                        className="mt-3 inline-block text-sm underline"
                    >
                        Download office list (Excel)
                    </a>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        {editingOffice ? 'Edit office' : 'Add office'}
                    </h2>

                    <form
                        key={editingOffice?.id ?? 'new'}
                        onSubmit={submitOffice}
                        className="grid gap-4 md:grid-cols-2"
                    >
                        <div>
                            <label htmlFor="code" className="mb-1 block text-sm font-medium">
                                Office code
                            </label>

                            <input
                                id="code"
                                value={data.code}
                                onChange={(event) =>
                                    setData('code', event.target.value)
                                }
                                maxLength={30}
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
                            <label htmlFor="name" className="mb-1 block text-sm font-medium">
                                Office name
                            </label>

                            <input
                                id="name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                maxLength={150}
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
                            <label htmlFor="type" className="mb-1 block text-sm font-medium">
                                Office type
                            </label>

                            <select
                                id="type"
                                value={data.type}
                                onChange={(event) =>
                                    setData('type', event.target.value)
                                }
                                className="w-full rounded-md border bg-background p-2"
                            >
                                <option value="provincial">Provincial</option>
                                <option value="zonal">Zonal</option>
                            </select>

                            {errors.type && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.type}
                                </p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="is_active" className="mb-1 block text-sm font-medium">
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
                                {editingOffice ? 'Save changes' : 'Create office'}
                            </button>

                            {editingOffice && (
                                <Link href="/offices" className="text-sm underline">
                                    Cancel editing
                                </Link>
                            )}
                        </div>
                    </form>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">Offices</h2>

                    <form
                        onSubmit={searchOffices}
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        <label htmlFor="search" className="sr-only">
                            Search offices
                        </label>

                        <input
                            id="search"
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder="Search by code or name"
                            className="min-w-48 flex-1 rounded-md border bg-background p-2"
                        />

                        <button
                            type="submit"
                            className="rounded-md border px-4 py-2"
                        >
                            Search
                        </button>

                        <Link href="/offices" className="rounded-md border px-4 py-2">
                            Clear
                        </Link>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-2">Code</th>
                                    <th className="p-2">Name</th>
                                    <th className="p-2">Type</th>
                                    <th className="p-2">Status</th>
                                    <th className="p-2">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                {offices.map((office) => (
                                    <tr key={office.id} className="border-b">
                                        <td className="p-2">{office.code}</td>
                                        <td className="p-2">{office.name}</td>
                                        <td className="p-2">{office.type}</td>
                                        <td className="p-2">
                                            {office.is_active ? 'Active' : 'Inactive'}
                                        </td>
                                        <td className="p-2">
                                            <Link
                                                href={`/offices/${office.id}/edit`}
                                                className="underline"
                                            >
                                                Edit
                                            </Link>
                                        </td>
                                    </tr>
                                ))}

                                {offices.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="p-2">
                                            No offices found.
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
