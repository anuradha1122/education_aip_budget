import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type ExpenseCategory = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

type Props = {
    categories: ExpenseCategory[];
    filters: {
        search: string;
    };
};

type CategoryForm = {
    code: string;
    name: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expense categories', href: '/expense-categories' },
];

export default function ExpenseCategoryIndex({ categories, filters }: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm<CategoryForm>({
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

    function editCategory(category: ExpenseCategory) {
        clearErrors();
        setEditingId(category.id);
        setData({
            code: category.code,
            name: category.name,
            is_active: category.is_active,
        });

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingId === null) {
            post('/expense-categories', {
                onSuccess: clearForm,
            });
        } else {
            put(`/expense-categories/${editingId}`, {
                onSuccess: clearForm,
            });
        }
    }

    function searchCategories(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/expense-categories',
            { search },
            { preserveState: true, replace: true },
        );
    }

    function clearSearch() {
        setSearch('');
        router.get('/expense-categories');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expense categories" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Expense category management
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Create, update and deactivate expense categories.
                    </p>

                    <div>
                        <a
                            href="/expense-categories/import/template"
                            className="mt-4 inline-block underline"
                        >
                            Download expense category import template
                        </a>

                        <a href="/expense-categories/import" className="underline">
                            Validate expense category import
                        </a>
                    </div>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        {editingId === null
                            ? 'Add expense category'
                            : 'Edit expense category'}
                    </h2>

                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="code"
                                    className="mb-1 block font-medium"
                                >
                                    Category code
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
                                    Category name
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
                                      ? 'Create category'
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
                        Expense categories
                    </h2>

                    <form
                        onSubmit={searchCategories}
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        <input
                            type="search"
                            aria-label="Search expense categories"
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
                                {categories.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="p-2">
                                            No expense categories found.
                                        </td>
                                    </tr>
                                ) : (
                                    categories.map((category) => (
                                        <tr
                                            key={category.id}
                                            className="border-b"
                                        >
                                            <td className="p-2">
                                                {category.code}
                                            </td>
                                            <td className="p-2">
                                                {category.name}
                                            </td>
                                            <td className="p-2">
                                                {category.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </td>
                                            <td className="p-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        editCategory(category)
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