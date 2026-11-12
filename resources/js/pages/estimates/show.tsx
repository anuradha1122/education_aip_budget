import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Item = { id: number; description: string; rate_in_cents: number | string; units: number; people: number };
type Props = { estimate: { id: number; title: string; circular_paragraph: string | null }; items: Item[]; totalInCents: string };
const money = (cents: bigint) => `${cents / 100n}.${(cents % 100n).toString().padStart(2, '0')}`;

export default function ShowEstimate({ estimate, items, totalInCents }: Props) {
    return <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'Estimate', href: `/estimates/${estimate.id}` }]}>
        <Head title="Estimate" />
        <div className="flex flex-col gap-5 p-4 md:p-6">
            <div><h1 className="text-2xl font-semibold">{estimate.title}</h1><p className="mt-2 text-sm">Estimate #{estimate.id}</p></div>
            <Link href="/estimates/create" className="underline">Create another estimate</Link>
            <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="p-2">Description</th><th className="p-2">Rate (Rs.)</th><th className="p-2">Days / hours</th><th className="p-2">People</th><th className="p-2">Amount (Rs.)</th></tr></thead>
                <tbody>{items.map((item) => <tr key={item.id} className="border-b"><td className="p-2">{item.description}</td><td className="p-2">{money(BigInt(item.rate_in_cents))}</td><td className="p-2">{item.units}</td><td className="p-2">{item.people}</td><td className="p-2">{money(BigInt(item.rate_in_cents) * BigInt(item.units) * BigInt(item.people))}</td></tr>)}</tbody></table></div>
            <p className="font-semibold">Total: Rs. {money(BigInt(totalInCents))}</p>
            <div><h2 className="font-medium">Circular paragraph</h2><p className="mt-2 whitespace-pre-wrap">{estimate.circular_paragraph || 'Not entered'}</p></div>
        </div>
    </AppLayout>;
}
