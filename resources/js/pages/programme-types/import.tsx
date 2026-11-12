import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type ImportRow = {
    row: number;
    code: string;
    name: string;
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
};

type UploadForm = {
    file: File | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Programme types', href: '/programme-types' },
    { title: 'Validate import', href: '/programme-types/import' },
];

export default function ProgrammeTypeImport({ result }: Props) {
    const { data, setData, post, processing, errors } = useForm<UploadForm>({
        file: null,
    });

    const [reviewedFile, setReviewedFile] = useState<File | null>(null);

    const importForm = useForm<UploadForm>({
        file: null,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        setReviewedFile(null);
        importForm.clearErrors();

        const fileToReview = data.file;

        post('/programme-types/import/validate', {
            forceFormData: true,
            onSuccess: () => {
                setReviewedFile(fileToReview);
            },
        });
    }

    function importProgrammeTypes() {
        if (!reviewedFile || reviewedFile !== data.file) {
            return;
        }

        importForm.transform(() => ({
            file: reviewedFile,
        }));

        importForm.post('/programme-types/import', {
            forceFormData: true,
            onError: () => {
                setReviewedFile(null);
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Validate programme type import" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Validate programme type import
                    </h1>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Upload the completed Excel template to check its
                        contents. This step does not save any programme types.
                    </p>
                </div>

                <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <a href="/programme-types/import/template" className="underline">
                        Download template
                    </a>

                    <Link href="/programme-types" className="underline">
                        Back to programme types
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
                        onChange={(event) => {
                            setReviewedFile(null);
                            importForm.clearErrors();
                            setData('file', event.target.files?.[0] ?? null);
                        }}
                        disabled={processing || importForm.processing}
                        required
                    />

                    <p className="text-sm text-muted-foreground">
                        XLSX only; maximum 2 MB and 500 data rows.
                    </p>

                    <p className="text-sm text-muted-foreground">
                        For status, enter Active or Inactive.
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
                            No programme types have been saved.
                        </p>

                        {importForm.errors.file && (
                            <p className="mt-3 text-sm text-red-600">
                                {importForm.errors.file}
                            </p>
                        )}

                        {result.invalid === 0 &&
                            reviewedFile !== null &&
                            reviewedFile === data.file && (
                                <div className="mt-4">
                                    <p className="mb-2 text-sm">
                                        Review the rows below before importing.
                                    </p>

                                    <button
                                        type="button"
                                        onClick={importProgrammeTypes}
                                        disabled={importForm.processing || processing}
                                        className="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                                    >
                                        {importForm.processing ? 'Importing...' : 'Import programme types'}
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
                                        <th className="p-2">Status</th>
                                        <th className="p-2">Result</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {result.rows.map((row) => (
                                        <tr key={row.row} className="border-b">
                                            <td className="p-2">{row.row}</td>
                                            <td className="p-2">{row.code}</td>
                                            <td className="p-2">
                                                {row.name}
                                            </td>
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