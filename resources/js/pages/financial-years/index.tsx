import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type FinancialYear = {
    id: number;
    year: number;
    start_date: string;
    end_date: string;
    is_closed: boolean;
};

type Props = {
    financialYears: FinancialYear[];
    editingYear: FinancialYear | null;
};

type YearForm = {
    year: string;
    start_date: string;
    end_date: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Financial years', href: '/financial-years' },
];

export default function FinancialYearIndex(props: Props) {
    return (
        <FinancialYearPage
            key={props.editingYear?.id ?? 'new'}
            {...props}
        />
    );
}

function FinancialYearPage({ financialYears, editingYear }: Props) {
    const { data, setData, post, put, processing, errors, reset } =
        useForm<YearForm>({
            year: editingYear ? String(editingYear.year) : '',
            start_date: editingYear?.start_date ?? '',
            end_date: editingYear?.end_date ?? '',
        });

    const [reopeningYear, setReopeningYear] = useState<FinancialYear | null>(null);
    const reopenForm = useForm({ reason: '' });

    function submitReopen(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!reopeningYear || !window.confirm(`Reopen financial year ${reopeningYear.year}?`)) return;
        reopenForm.patch(`/financial-years/${reopeningYear.id}/reopen`, {
            preserveScroll: true,
            onSuccess: () => { setReopeningYear(null); reopenForm.reset(); },
        });
    }

    function closeYear(year: FinancialYear) {
        if (window.confirm(`Close financial year ${year.year}? Reopening requires an administrator and a reason.`)) {
            router.patch(`/financial-years/${year.id}/close`, {}, { preserveScroll: true });
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (editingYear) {
            put(`/financial-years/${editingYear.id}`);
        } else {
            post('/financial-years', {
                onSuccess: () => reset(),
            });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Financial years" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Financial years
                    </h1>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Create and view financial years.
                    </p>

                    <a
                        href="/financial-year-audit"
                        className="mt-3 inline-block underline"
                    >
                        View financial year audit history
                    </a>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        {editingYear ? 'Edit financial year' : 'Add financial year'}
                    </h2>

                    <form
                        onSubmit={submit}
                        className="grid gap-4 md:grid-cols-3"
                    >
                        <div>
                            <label
                                htmlFor="year"
                                className="mb-1 block text-sm font-medium"
                            >
                                Year
                            </label>

                            <input
                                id="year"
                                type="number"
                                min="1"
                                max="65535"
                                value={data.year}
                                onChange={(event) =>
                                    setData('year', event.target.value)
                                }
                                required
                                className="w-full rounded-md border bg-background p-2"
                            />

                            {errors.year && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.year}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="start_date"
                                className="mb-1 block text-sm font-medium"
                            >
                                Start date
                            </label>

                            <input
                                id="start_date"
                                type="date"
                                value={data.start_date}
                                onChange={(event) =>
                                    setData('start_date', event.target.value)
                                }
                                required
                                className="w-full rounded-md border bg-background p-2"
                            />

                            {errors.start_date && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.start_date}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="end_date"
                                className="mb-1 block text-sm font-medium"
                            >
                                End date
                            </label>

                            <input
                                id="end_date"
                                type="date"
                                value={data.end_date}
                                onChange={(event) =>
                                    setData('end_date', event.target.value)
                                }
                                required
                                className="w-full rounded-md border bg-background p-2"
                            />

                            {errors.end_date && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.end_date}
                                </p>
                            )}
                        </div>

                        <div className="md:col-span-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                            >
                                {processing
                                    ? 'Saving...'
                                    : editingYear
                                    ? 'Save changes'
                                    : 'Create financial year'}
                            </button>

                            {editingYear && (
                                <Link
                                    href="/financial-years"
                                    className="ml-3 text-sm underline"
                                >
                                    Cancel editing
                                </Link>
                            )}
                        </div>
                    </form>
                </section>

                {reopeningYear && <section className="rounded-lg border p-4">
                    <h2 className="mb-2 text-lg font-semibold">Reopen financial year {reopeningYear.year}</h2>
                    <p className="mb-3 text-sm text-muted-foreground">Provide a reason. Reopening allows changes in workflows that use the financial year status.</p>
                    <form onSubmit={submitReopen} className="space-y-3">
                        <label htmlFor="reopen-reason" className="block text-sm font-medium">Reason for reopening</label>
                        <textarea id="reopen-reason" value={reopenForm.data.reason} onChange={event => reopenForm.setData('reason', event.target.value)} maxLength={500} required className="w-full rounded-md border bg-background p-2" />
                        {reopenForm.errors.reason && <p role="alert" className="text-sm text-red-600">{reopenForm.errors.reason}</p>}
                        <div className="flex gap-2"><button disabled={reopenForm.processing} className="rounded border px-4 py-2 hover:bg-accent disabled:opacity-50">Reopen year</button><button type="button" onClick={() => { setReopeningYear(null); reopenForm.reset(); }} className="rounded border px-4 py-2 hover:bg-accent">Cancel</button></div>
                    </form>
                </section>}

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        Financial year list
                    </h2>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-2">Year</th>
                                    <th className="p-2">Start date</th>
                                    <th className="p-2">End date</th>
                                    <th className="p-2">Status</th>
                                    <th className="p-2">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                {financialYears.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="p-2">
                                            No financial years found.
                                        </td>
                                    </tr>
                                ) : (
                                    financialYears.map((financialYear) => (
                                        <tr
                                            key={financialYear.id}
                                            className="border-b"
                                        >
                                            <td className="p-2">
                                                {financialYear.year}
                                            </td>
                                            <td className="p-2">
                                                {financialYear.start_date}
                                            </td>
                                            <td className="p-2">
                                                {financialYear.end_date}
                                            </td>
                                            <td className="p-2">
                                                {financialYear.is_closed
                                                    ? 'Closed'
                                                    : 'Open'}
                                            </td>
                                            <td className="p-2">
                                                {financialYear.is_closed ? (
                                                    <button type="button" onClick={() => { reopenForm.clearErrors(); reopenForm.reset(); setReopeningYear(financialYear); }} className="inline-flex rounded border px-3 py-1.5 hover:bg-accent">Reopen year</button>
                                                ) : (
                                                    <div className="flex flex-wrap gap-2">
                                                        <Link href={`/financial-years/${financialYear.id}/edit`} className="inline-flex rounded border px-3 py-1.5 hover:bg-accent">Edit</Link>
                                                        <button type="button" onClick={() => closeYear(financialYear)} className="inline-flex rounded border px-3 py-1.5 hover:bg-accent">Close year</button>
                                                    </div>
                                                )}
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