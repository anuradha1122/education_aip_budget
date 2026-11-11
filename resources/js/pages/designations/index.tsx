import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Designation = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

type Props = {
    designations: Designation[];
    filters: {
        search: string;
    };
};

type DesignationForm = {
    code: string;
    name: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Designations', href: '/designations' },
];

export default function DesignationIndex({ designations, filters }: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm<DesignationForm>({
            code: '',
            name: '',
            is_active: true,
        });

    function clearForm() {
        setEditingId(null);
        clearErrors();
        setData({
            code: '',
            name: '',
            is_active: true,
        });
    }

    function editDesignation(designation: Designation) {
        clearErrors();
        setEditingId(designation.id);
        setData({
            code: designation.code,
            name: designation.name,
            is_active: designation.is_active,
        });

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingId === null) {
            post('/designations', {
                onSuccess: clearForm,
            });
        } else {
            put(`/designations/${editingId}`, {
                onSuccess: clearForm,
            });
        }
    }

    function searchDesignations(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/designations',
            { search },
            { preserveState: true, replace: true },
        );
    }

    function clearSearch() {
        setSearch('');
        router.get('/designations');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Designations" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Designation management
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Create, update and deactivate designations.
                    </p>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        {editingId === null
                            ? 'Add designation'
                            : 'Edit designation'}
                    </h2>

                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="code"
                                    className="mb-1 block font-medium"
                                >
                                    Designation code
                                </label>

                                <input
                                    id="code"
                                    type="text"
                                    value={data.code}
                                    onChange={(event) =>
                                        setData('code', event.target.value)
                                    }
                                    maxLength={50}
                                    required
                                    className="w-full rounded-md border bg-background px-3 py-2"
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
                                    className="mb-1 block font-medium"
                                >
                                    Designation name
                                </label>

                                <input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    maxLength={255}
                                    required
                                    className="w-full rounded-md border bg-background px-3 py-2"
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
                                    className="mb-1 block font-medium"
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
                                    className="w-full rounded-md border bg-background px-3 py-2"
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
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                            >
                                {processing
                                    ? 'Saving...'
                                    : editingId === null
                                      ? 'Create designation'
                                      : 'Save changes'}
                            </button>

                            {editingId !== null && (
                                <button
                                    type="button"
                                    onClick={clearForm}
                                    disabled={processing}
                                    className="rounded-md border px-4 py-2"
                                >
                                    Cancel
                                </button>
                            )}
                        </div>
                    </form>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        Designations
                    </h2>

                    <form
                        onSubmit={searchDesignations}
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        <input
                            type="search"
                            aria-label="Search designations"
                            placeholder="Search by code or name"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="min-w-0 flex-1 rounded-md border bg-background px-3 py-2"
                        />

                        <button
                            type="submit"
                            className="rounded-md border px-4 py-2"
                        >
                            Search
                        </button>

                        <button
                            type="button"
                            onClick={clearSearch}
                            className="rounded-md border px-4 py-2"
                        >
                            Clear
                        </button>
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
                                {designations.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="p-2">
                                            No designations found.
                                        </td>
                                    </tr>
                                ) : (
                                    designations.map((designation) => (
                                        <tr
                                            key={designation.id}
                                            className="border-b"
                                        >
                                            <td className="p-2">
                                                {designation.code}
                                            </td>
                                            <td className="p-2">
                                                {designation.name}
                                            </td>
                                            <td className="p-2">
                                                {designation.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </td>
                                            <td className="p-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        editDesignation(
                                                            designation,
                                                        )
                                                    }
                                                    className="underline"
                                                >
                                                    Edit
                                                </button>
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