import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Option = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

type Officer = {
    id: number;
    office_id: number;
    designation_id: number;
    name: string;
    is_active: boolean;
    office: Pick<Option, 'id' | 'code' | 'name'>;
    designation: Pick<Option, 'id' | 'code' | 'name'>;
};

type Props = {
    officers: Officer[];
    offices: Option[];
    designations: Option[];
    filters: {
        search: string;
    };
};

type OfficerForm = {
    office_id: string;
    designation_id: string;
    name: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Officers', href: '/officers' },
];

export default function OfficerIndex({
    officers,
    offices,
    designations,
    filters,
}: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm<OfficerForm>({
            office_id: '',
            designation_id: '',
            name: '',
            is_active: true,
        });

    function clearForm() {
        setEditingId(null);
        clearErrors();
        setData({
            office_id: '',
            designation_id: '',
            name: '',
            is_active: true,
        });
    }

    function editOfficer(officer: Officer) {
        clearErrors();
        setEditingId(officer.id);
        setData({
            office_id: String(officer.office_id),
            designation_id: String(officer.designation_id),
            name: officer.name,
            is_active: officer.is_active,
        });

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingId === null) {
            post('/officers', {
                onSuccess: clearForm,
            });
        } else {
            put(`/officers/${editingId}`, {
                onSuccess: clearForm,
            });
        }
    }

    function searchOfficers(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/officers',
            { search },
            { preserveState: true, replace: true },
        );
    }

    function clearSearch() {
        setSearch('');
        router.get('/officers');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Officers" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Officer management
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Create, update and deactivate officers.
                    </p>

                    <div>
                        <a href="/officers/audit" className="mt-4 inline-block underline">
                            View officer audit history
                        </a>
                    </div>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        {editingId === null ? 'Add officer' : 'Edit officer'}
                    </h2>

                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="name"
                                    className="mb-1 block font-medium"
                                >
                                    Officer name
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
                                    htmlFor="office_id"
                                    className="mb-1 block font-medium"
                                >
                                    Office
                                </label>

                                <select
                                    id="office_id"
                                    value={data.office_id}
                                    onChange={(event) =>
                                        setData('office_id', event.target.value)
                                    }
                                    required
                                    className="w-full rounded-md border bg-background px-3 py-2"
                                >
                                    <option value="">Select an office</option>

                                    {offices.map((office) => (
                                        <option key={office.id} value={office.id}>
                                            {office.code} — {office.name}
                                            {!office.is_active ? ' (Inactive)' : ''}
                                        </option>
                                    ))}
                                </select>

                                {errors.office_id && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.office_id}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label
                                    htmlFor="designation_id"
                                    className="mb-1 block font-medium"
                                >
                                    Designation
                                </label>

                                <select
                                    id="designation_id"
                                    value={data.designation_id}
                                    onChange={(event) =>
                                        setData(
                                            'designation_id',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    className="w-full rounded-md border bg-background px-3 py-2"
                                >
                                    <option value="">
                                        Select a designation
                                    </option>

                                    {designations.map((designation) => (
                                        <option
                                            key={designation.id}
                                            value={designation.id}
                                        >
                                            {designation.code} —{' '}
                                            {designation.name}
                                            {!designation.is_active
                                                ? ' (Inactive)'
                                                : ''}
                                        </option>
                                    ))}
                                </select>

                                {errors.designation_id && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.designation_id}
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
                                      ? 'Create officer'
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
                    <h2 className="mb-4 text-lg font-semibold">Officers</h2>

                    <form
                        onSubmit={searchOfficers}
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        <input
                            type="search"
                            aria-label="Search officers"
                            placeholder="Search by name"
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
                                    <th className="p-2">Name</th>
                                    <th className="p-2">Office</th>
                                    <th className="p-2">Designation</th>
                                    <th className="p-2">Status</th>
                                    <th className="p-2">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                {officers.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-2">
                                            No officers found.
                                        </td>
                                    </tr>
                                ) : (
                                    officers.map((officer) => (
                                        <tr key={officer.id} className="border-b">
                                            <td className="p-2">
                                                {officer.name}
                                            </td>
                                            <td className="p-2">
                                                {officer.office.code} —{' '}
                                                {officer.office.name}
                                            </td>
                                            <td className="p-2">
                                                {officer.designation.code} —{' '}
                                                {officer.designation.name}
                                            </td>
                                            <td className="p-2">
                                                {officer.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </td>
                                            <td className="p-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        editOfficer(officer)
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