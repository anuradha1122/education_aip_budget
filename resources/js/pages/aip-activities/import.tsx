import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Year = {
    id: number;
    year: number;
};

type Office = { id: number; code: string; name: string; is_active: boolean | number };

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
    offices: Office[];
    result: {
        financial_year: number;
        office: { code: string; name: string };
        rows: ImportRow[];
    } | null;
};

export default function AipImport({ years, offices, result }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        financial_year_id: string;
        office_id: string;
        file: File | null;
    }>({
        financial_year_id: '',
        office_id: '',
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
                        Office
                        <select required value={data.office_id}
                            onChange={(event) => setData('office_id', event.target.value)}
                            className="mt-1 block w-full rounded border bg-background p-2">
                            <option value="">Select an office</option>
                            {offices.map((office) => (
                                <option key={office.id} value={office.id}>
                                    {office.code} — {office.name}{Number(office.is_active) === 0 ? ' (inactive)' : ''}
                                </option>
                            ))}
                        </select>
                    </label>
                    {errors.office_id && <p className="text-sm text-red-600">{errors.office_id}</p>}

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

                        <p className="mt-2 text-sm">Office: {result.office.code} — {result.office.name}</p>
                        <p className="mt-2 text-sm">
                            This preliminary check covers activity numbers, descriptions, supplied vote and fund codes, and supplied subject codes for the selected office.
                            Subject code, officer, vote and fund are required. Supplied officer names are checked within the selected office; supplied designations are compared with the matching officer. When both the officer and subject match uniquely, their active assignment is checked. For an existing activity, supplied known funds and votes are checked against its funding entries. Subject names and inactive references are not yet checked. No rows have been saved.
                        </p>

                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead><tr className="border-b">
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
                                </tr></thead>
                                <tbody>{result.rows.map((row) => (
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
                                        <td className="p-2">{row.errors.length > 0 ? row.errors.join(' ') : 'None found'}</td>
                                    </tr>
                                ))}</tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
