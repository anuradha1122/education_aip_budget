import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type ImportRow = {
    row: number;
    code: string;
    name: string;
    type: string;
    status: string;
    errors: string[];
};

type ImportResult = {
    rows: ImportRow[];
    total: number;
    invalid: number;
};

type Props = {
    result: ImportResult | null;
    importMessage: string | null;
};

type UploadForm = {
    file: File | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Offices', href: '/offices' },
    { title: 'Validate import', href: '/offices/import' },
];

export default function OfficeImport({ result, importMessage }: Props) {
    const { data, setData, post, processing, errors } =
        useForm<UploadForm>({
            file: null,
        });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post('/offices/import/validate', {
            forceFormData: true,
        });
    }

    function importOffices() {
        if (!data.file) {
            return;
        }

        const confirmed = window.confirm(
            'Import the selected workbook? The file will be validated again before saving.',
        );

        if (!confirmed) {
            return;
        }

        post('/offices/import', {
            forceFormData: true,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Validate office import" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Validate office import
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Upload the completed Excel template to check its
                        contents. This step does not save any offices.
                    </p>
                </div>

                <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <a
                        href="/offices/import/template"
                        className="underline"
                    >
                        Download template
                    </a>

                    <Link href="/offices" className="underline">
                        Back to offices
                    </Link>
                </div>

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-4 rounded-lg border p-4"
                >
                    <label htmlFor="file" className="font-medium">
                        Excel workbook
                    </label>

                    <input
                        id="file"
                        type="file"
                        accept=".xlsx"
                        onChange={(event) =>
                            setData(
                                'file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                        required
                    />

                    <p className="text-sm text-muted-foreground">
                        XLSX only; maximum 2 MB and 500 data rows.
                    </p>

                    {errors.file && (
                        <p className="text-sm text-red-600">
                            {errors.file}
                        </p>
                    )}

                    <div>
                        <button
                            type="submit"
                            disabled={processing || !data.file}
                            className="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                        >
                            {processing ? 'Checking...' : 'Validate file'}
                        </button>
                    </div>
                </form>

                {importMessage && (
                    <p
                        role="status"
                        className="rounded-lg border p-4 text-sm"
                    >
                        {importMessage}
                    </p>
                )}

                {result && (
                    <section className="rounded-lg border p-4">
                        <h2 className="text-lg font-semibold">
                            Validation results
                        </h2>

                        <p className="mt-2 text-sm">
                            Rows checked: {result.total}. Rows with errors:{' '}
                            {result.invalid}.
                        </p>

                        <p className="mt-1 text-sm">
                            No offices have been saved.
                        </p>

                        {result.invalid === 0 && (
                            <div className="mt-4">
                                <p className="mb-2 text-sm">
                                    Review the rows above before importing. If the file selection
                                    was cleared, select the same workbook again.
                                </p>

                                <button
                                    type="button"
                                    onClick={importOffices}
                                    disabled={processing || !data.file}
                                    className="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                                >
                                    Import offices
                                </button>
                            </div>
                        )}

                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-2">Row</th>
                                        <th className="p-2">Code</th>
                                        <th className="p-2">Name</th>
                                        <th className="p-2">Type</th>
                                        <th className="p-2">Status</th>
                                        <th className="p-2">Result</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {result.rows.map((row) => (
                                        <tr key={row.row} className="border-b">
                                            <td className="p-2">{row.row}</td>
                                            <td className="p-2">{row.code}</td>
                                            <td className="p-2">{row.name}</td>
                                            <td className="p-2">{row.type}</td>
                                            <td className="p-2">{row.status}</td>
                                            <td className="p-2">
                                                {row.errors.length === 0 ? (
                                                    'Valid'
                                                ) : (
                                                    <ul className="list-disc pl-4 text-red-600">
                                                        {row.errors.map(
                                                            (error, index) => (
                                                                <li key={index}>
                                                                    {error}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
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
