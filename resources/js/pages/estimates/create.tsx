import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Row = { description: string; rate: string; units: string; people: string };
const emptyRow = (): Row => ({ description: '', rate: '', units: '1', people: '1' });
const money = (cents: bigint) => `${cents / 100n}.${(cents % 100n).toString().padStart(2, '0')}`;

function parseRow(row: Row): { rate: bigint; units: bigint; people: bigint } | null {
    if (!/^\d{1,10}(\.\d{1,2})?$/.test(row.rate) || !/^\d{1,7}$/.test(row.units) || !/^\d{1,7}$/.test(row.people)) return null;
    const [whole, fraction = ''] = row.rate.split('.');
    const rate = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0') || '0');
    const units = BigInt(row.units);
    const people = BigInt(row.people);
    return rate <= 100000000000n && units <= 1000000n && people <= 1000000n ? { rate, units, people } : null;
}

export default function CreateEstimate({ defaultCircularParagraph }: { defaultCircularParagraph: string }) {
    const [title, setTitle] = useState('');
    const [paragraph, setParagraph] = useState(defaultCircularParagraph);
    const [rows, setRows] = useState<Row[]>([emptyRow()]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const parsed = rows.map(parseRow);
    const validAmounts = parsed.every((row) => row !== null);
    const total = parsed.reduce<bigint>((sum, row) => sum + (row ? row.rate * row.units * row.people : 0n), 0n);
    function updateRow(index: number, key: keyof Row, value: string) {
        setRows((current) => current.map((row, i) => i === index ? { ...row, [key]: value } : row));
    }
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!validAmounts || parsed.some((row) => row === null)) {
            setErrors({ items: 'Enter a valid non-negative rate (up to two decimal places), whole units and people for each row.' });
            return;
        }
        setErrors({});
        router.post('/estimates', {
            title, circular_paragraph: paragraph,
            items: rows.map((row, i) => ({ description: row.description, rate_in_cents: Number(parsed[i]!.rate), units: Number(parsed[i]!.units), people: Number(parsed[i]!.people) })),
        }, { onStart: () => setSaving(true), onFinish: () => setSaving(false), onError: (e) => setErrors(e) });
    }
    return <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'Create estimate', href: '/estimates/create' }]}>
        <Head title="Create estimate" />
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <div><h1 className="text-2xl font-semibold">Create estimate</h1><p className="mt-2 text-sm text-muted-foreground">Enter the expense details for this estimate.</p></div>
            <form onSubmit={submit} className="flex flex-col gap-5">
                <label className="block">Programme title<input className="mt-1 block w-full rounded border bg-background p-2" value={title} maxLength={255} required onChange={(e) => setTitle(e.target.value)} /></label>
                {errors.title && <p className="text-red-600">{errors.title}</p>}
                {rows.map((row, i) => <fieldset key={i} className="grid gap-3 rounded border p-4 md:grid-cols-4">
                    <legend>Item {i + 1}</legend>
                    <label>Description<input className="mt-1 block w-full rounded border bg-background p-2" value={row.description} maxLength={255} required onChange={(e) => updateRow(i, 'description', e.target.value)} /></label>
                    <label>Rate (Rs.)<input className="mt-1 block w-full rounded border bg-background p-2" inputMode="decimal" value={row.rate} required onChange={(e) => {
    const value = e.target.value;

    if (/^\d*(?:\.\d{0,2})?$/.test(value)) {
        updateRow(i, 'rate', value);
    }
}} /></label>
                    <label>Days / hours<input className="mt-1 block w-full rounded border bg-background p-2" inputMode="numeric" value={row.units} required onChange={(e) => updateRow(i, 'units', e.target.value)} /></label>
                    <label>People<input className="mt-1 block w-full rounded border bg-background p-2" inputMode="numeric" value={row.people} required onChange={(e) => updateRow(i, 'people', e.target.value)} /></label>
                    <p className="text-sm">Line total: {parsed[i] ? `Rs. ${money(parsed[i]!.rate * parsed[i]!.units * parsed[i]!.people)}` : 'Enter valid amounts'}</p>
                    <button type="button" className="underline" disabled={rows.length === 1 || saving} onClick={() => setRows(rows.filter((_, index) => index !== i))}>Remove item</button>
                </fieldset>)}
                <div><button type="button" className="underline" disabled={rows.length >= 200 || saving} onClick={() => setRows([...rows, emptyRow()])}>Add item</button></div>
                <p className="font-semibold">Total: {validAmounts ? `Rs. ${money(total)}` : 'Enter valid amounts'}</p>
                <p className="text-sm text-muted-foreground">For a fixed amount, enter the amount as the rate and use 1 for days / hours and people.</p>
                <label>Circular paragraph<textarea className="mt-1 block w-full rounded border bg-background p-2" rows={5} maxLength={10000} value={paragraph} onChange={(e) => setParagraph(e.target.value)} /></label>
                {Object.entries(errors).map(([key, value]) => key !== 'title' && <p key={key} className="text-sm text-red-600">{value}</p>)}
                <div><button className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50" disabled={saving} type="submit">{saving ? 'Saving...' : 'Save estimate'}</button></div>
            </form>
        </div>
    </AppLayout>;
}
