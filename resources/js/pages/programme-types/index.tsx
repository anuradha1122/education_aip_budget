import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type ProgrammeType = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

type Props = {
    programmeTypes: ProgrammeType[];
    filters: {
        search: string;
    };
};

type ProgrammeTypeForm = {
    code: string;
    name: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Programme types', href: '/programme-types' },
];

export default function ProgrammeTypeIndex({
    programmeTypes,
    filters,
}: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm<ProgrammeTypeForm>({
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

    function editProgrammeType(programmeType: ProgrammeType) {
        clearErrors();
        setEditingId(programmeType.id);
        setData({
            code: programmeType.code,
            name: programmeType.name,
            is_active: programmeType.is_active,
        });

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingId === null) {
            post('/programme-types', {
                onSuccess: clearForm,
            });
        } else {
            put(`/programme-types/${editingId}`, {
                onSuccess: clearForm,
            });
        }
    }

    function searchProgrammeTypes(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/programme-types',
            { search },
            { preserveState: true, replace: true },
        );
    }

    function clearSearch() {
        setSearch('');
        router.get('/programme-types');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Programme types" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Programme type management
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Create, update and deactivate programme types.
                    </p>

                    <a href="/programme-types/audit" className="mt-4 inline-block underline">
                        View programme type audit history
                    </a>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        {editingId === null
                            ? 'Add programme type'
                            : 'Edit programme type'}
                    </h2>

                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="code"
                                    className="mb-1 block font-medium"
                                >
                                    Programme type code
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
                                    Programme type name
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
                                      ? 'Create programme type'
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
                        Programme types
                    </h2>

                    <form
                        onSubmit={searchProgrammeTypes}
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        <input
                            type="search"
                            aria-label="Search programme types"
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
                                {programmeTypes.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="p-2">
                                            No programme types found.
                                        </td>
                                    </tr>
                                ) : (
                                    programmeTypes.map((programmeType) => (
                                        <tr
                                            key={programmeType.id}
                                            className="border-b"
                                        >
                                            <td className="p-2">
                                                {programmeType.code}
                                            </td>
                                            <td className="p-2">
                                                {programmeType.name}
                                            </td>
                                            <td className="p-2">
                                                {programmeType.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </td>
                                            <td className="p-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        editProgrammeType(
                                                            programmeType,
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