import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Props = {
    settings: { circular_number: string; rule_paragraph: string };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Estimate circular settings', href: '/estimate-circular-settings' },
];

export default function EstimateCircularSettings({ settings }: Props) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        circular_number: settings.circular_number,
        rule_paragraph: settings.rule_paragraph,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        put('/estimate-circular-settings');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Estimate circular settings" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Estimate circular settings</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Set the common circular reference and rule paragraph for estimates.
                    </p>
                </div>
                <form onSubmit={submit} className="flex max-w-3xl flex-col gap-4 rounded-lg border p-4">
                    <div>
                        <label htmlFor="circular_number" className="block font-medium">Circular number</label>
                        <input id="circular_number" type="text" maxLength={255}
                            value={data.circular_number}
                            onChange={(event) => setData('circular_number', event.target.value)}
                            className="mt-2 w-full rounded-md border bg-background p-2" />
                        {errors.circular_number && <p className="mt-1 text-sm text-red-600">{errors.circular_number}</p>}
                    </div>
                    <div>
                        <label htmlFor="rule_paragraph" className="block font-medium">Rule paragraph</label>
                        <textarea id="rule_paragraph" rows={7} maxLength={10000}
                            value={data.rule_paragraph}
                            onChange={(event) => setData('rule_paragraph', event.target.value)}
                            className="mt-2 w-full rounded-md border bg-background p-2" />
                        {errors.rule_paragraph && <p className="mt-1 text-sm text-red-600">{errors.rule_paragraph}</p>}
                    </div>
                    <div>
                        <button type="submit" disabled={processing}
                            className="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50">
                            {processing ? 'Saving...' : 'Save settings'}
                        </button>
                    </div>
                    {recentlySuccessful && <p role="status" className="text-sm">Settings saved.</p>}
                </form>
            </div>
        </AppLayout>
    );
}
