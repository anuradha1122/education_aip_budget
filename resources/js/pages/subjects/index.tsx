import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Office = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

type Subject = {
    id: number;
    office_id: number;
    code: string;
    name: string;
    is_active: boolean;
    office?: Office | null;
};

type Props = {
    subjects: Subject[];
    offices: Office[];
    editingSubject: Subject | null;
};

type SubjectForm = {
    office_id: string;
    code: string;
    name: string;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Subjects', href: '/subjects' },
];

export default function SubjectIndex(props: Props) {
    return (
        <SubjectPage
            key={props.editingSubject?.id ?? 'new'}
            {...props}
        />
    );
}

function SubjectPage({ subjects, offices, editingSubject }: Props) {
    const { data, setData, post, put, processing, errors, reset } =
        useForm<SubjectForm>({
            office_id: editingSubject ? String(editingSubject.office_id) : '',
            code: editingSubject?.code ?? '',
            name: editingSubject?.name ?? '',
            is_active: editingSubject?.is_active ?? true,
        });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingSubject) {
            put(`/subjects/${editingSubject.id}`);
        } else {
            post('/subjects', {
                onSuccess: () => reset(),
            });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subjects" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Subject management
                    </h1>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Create, update and deactivate subjects.
                    </p>

                    <Link href="/subject-audit" className="mt-3 inline-block underline">
                        View subject audit history
                    </Link>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        {editingSubject ? 'Edit subject' : 'Add subject'}
                    </h2>

                    <form
                        onSubmit={submit}
                        className="grid gap-4 md:grid-cols-2"
                    >
                        <div>
                            <label
                                htmlFor="office_id"
                                className="mb-1 block text-sm font-medium"
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
                                className="w-full rounded-md border bg-background p-2"
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
                                htmlFor="code"
                                className="mb-1 block text-sm font-medium"
                            >
                                Subject code
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
                                Subject name
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
                                {editingSubject ? 'Save changes' : 'Create subject'}
                            </button>

                            {editingSubject && (
                                <Link href="/subjects" className="text-sm underline">
                                    Cancel editing
                                </Link>
                            )}
                        </div>
                    </form>
                </section>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">Subjects</h2>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-2">Office</th>
                                    <th className="p-2">Code</th>
                                    <th className="p-2">Name</th>
                                    <th className="p-2">Status</th>
                                    <th className="p-2">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                {subjects.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-2">
                                            No subjects found.
                                        </td>
                                    </tr>
                                ) : (
                                    subjects.map((subject) => (
                                        <tr key={subject.id} className="border-b">
                                            <td className="p-2">
                                                {subject.office
                                                    ? `${subject.office.code} — ${subject.office.name}`
                                                    : 'Not available'}
                                            </td>

                                            <td className="p-2">{subject.code}</td>
                                            <td className="p-2">{subject.name}</td>

                                            <td className="p-2">
                                                {subject.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </td>

                                            <td className="p-2">
                                                <Link
                                                    href={`/subjects/${subject.id}/edit`}
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