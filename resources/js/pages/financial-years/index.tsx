import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type FinancialYear = {
    id: number;
    year: number;
    start_date: string;
    end_date: string;
    is_closed: boolean;
};

type Props = {
    financialYears: FinancialYear[];
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

export default function FinancialYearIndex({ financialYears }: Props) {
    const { data, setData, post, processing, errors, reset } =
        useForm<YearForm>({
            year: '',
            start_date: '',
            end_date: '',
        });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post('/financial-years', {
            onSuccess: () => reset(),
        });
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
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-4 text-lg font-semibold">
                        Add financial year
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
                                {processing ? 'Creating...' : 'Create financial year'}
                            </button>
                        </div>
                    </form>
                </section>

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
                                </tr>
                            </thead>

                            <tbody>
                                {financialYears.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="p-2">
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