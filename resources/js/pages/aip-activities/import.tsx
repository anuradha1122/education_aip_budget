import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Year = {
    id: number;
    year: number;
};

type ImportRow = {
    row_number: number;
    serial_number: string;
    level: string;
    activity_number: string;
    activity: string;
    subject_code: string;
    subject: string;
    officer: string;
    designation: string;
    vote: string;
    fund: string;
    errors: string[];
};

type Props = {
    years: Year[];
    result: {
        financial_year: number;
        rows: ImportRow[];
    } | null;
};

export default function AipImport({ years, result }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        financial_year_id: string;
        file: File | null;
    }>({
        financial_year_id: '',
        file: null,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post('/aip-activities/import/validate', {
            forceFormData: true,
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: 'AIP activities', href: '/aip-activities' },
                { title: 'Import AIP', href: '/aip-activities/import' },
            ]}
        >
            <Head title="Validate AIP import" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Validate AIP import
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Select a financial year and review the workbook.
                        This step does not save activities.
                    </p>

                    <div className="mt-3 flex flex-wrap gap-6">
                        <Link href="/aip-activities" className="underline">
                            Back to AIP activities
                        </Link>

                        <a
                            href="/aip-activities/import/template"
                            className="underline"
                        >
                            Download template
                        </a>
                    </div>
                </div>

                <form
                    onSubmit={submit}
                    className="flex w-full max-w-2xl flex-col gap-4 rounded border p-4"
                >
                    <label>
                        Financial year
                        <select
                            required
                            value={data.financial_year_id}
                            onChange={(event) =>
                                setData('financial_year_id', event.target.value)
                            }
                            className="mt-1 block w-full rounded border bg-background p-2"
                        >
                            <option value="">Select an open year</option>

                            {years.map((year) => (
                                <option key={year.id} value={year.id}>
                                    {year.year}
                                </option>
                            ))}
                        </select>
                    </label>

                    {errors.financial_year_id && (
                        <p className="text-sm text-red-600">
                            {errors.financial_year_id}
                        </p>
                    )}

                    <label>
                        Excel workbook
                        <input
                            type="file"
                            accept=".xlsx"
                            required
                            onChange={(event) =>
                                setData('file', event.target.files?.[0] ?? null)
                            }
                            className="mt-1 block w-full"
                        />
                    </label>

                    {errors.file && (
                        <p className="text-sm text-red-600">{errors.file}</p>
                    )}

                    <div>
                        <button
                            type="submit"
                            disabled={processing || years.length === 0}
                            className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                        >
                            {processing ? 'Checking...' : 'Validate workbook'}
                        </button>
                    </div>
                </form>

                {result && (
                    <section>
                        <h2 className="text-lg font-semibold">
                            Review for {result.financial_year}
                        </h2>

                        <p className="mt-2 text-sm">
                            This preliminary check covers activity numbers, descriptions, and
                            whether supplied vote and fund codes exist. Subject, officer and
                            designation matching are not yet validated. Blank vote and fund
                            fields are not yet checked. No rows have been saved.
                        </p>

                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-2">Excel row</th>
                                        <th className="p-2">Serial number</th>
                                        <th className="p-2">Level</th>
                                        <th className="p-2">Activity number</th>
                                        <th className="p-2">Activity</th>
                                        <th className="p-2">Subject code</th>
                                        <th className="p-2">Subject</th>
                                        <th className="p-2">Officer</th>
                                        <th className="p-2">Designation</th>
                                        <th className="p-2">Vote</th>
                                        <th className="p-2">Fund</th>
                                        <th className="p-2">Preliminary errors</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {result.rows.map((row) => (
                                        <tr key={row.row_number} className="border-b">
                                            <td className="p-2">{row.row_number}</td>
                                            <td className="p-2">{row.serial_number}</td>
                                            <td className="p-2">{row.level}</td>
                                            <td className="p-2">{row.activity_number}</td>
                                            <td className="p-2">{row.activity}</td>
                                            <td className="p-2">{row.subject_code}</td>
                                            <td className="p-2">{row.subject}</td>
                                            <td className="p-2">{row.officer}</td>
                                            <td className="p-2">{row.designation}</td>
                                            <td className="p-2">{row.vote}</td>
                                            <td className="p-2">{row.fund}</td>
                                            <td className="p-2">
                                                {row.errors.length > 0
                                                    ? row.errors.join(' ')
                                                    : 'None found'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}