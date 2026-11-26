import { formatSriLankaTime } from '@/lib/sri-lanka-time';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

type Values = { name?: string; email?: string; role?: string; office_id?: number | null; officer_id?: number | null; is_active?: boolean; password_changed?: boolean; can_import_aip?: boolean } | null;
type Event = { id: number; action: string; actor_name: string | null; created_at: string; old_values: Values; new_values: Values };
type Offices = Record<string, { id: number; code: string; name: string }>;
type Props = { offices: Offices; subjectUser: { id: number; name: string; email: string }; events: { data: Event[]; links: { url: string | null; label: string; active: boolean }[] } };

function ValuesList({ values, offices }: { values: Values; offices: Offices }) {
    if (!values || Object.keys(values).length === 0) return <>—</>;
    const labels: Record<string, string> = { name: 'Name', email: 'Email', role: 'Role', office_id: 'Office', officer_id: 'Officer ID', is_active: 'Active', password_changed: 'Password changed', can_import_aip: 'AIP import permission' };
    return <dl>{Object.entries(values).map(([key, value]) => <div key={key}><dt className="inline font-medium">{labels[key] ?? key}: </dt><dd className="inline">{value === null ? '—' : key === 'office_id' ? (offices[String(value)] ? `${offices[String(value)].code} — ${offices[String(value)].name}` : `Office #${value} (unavailable)`) : typeof value === 'boolean' ? (value ? 'Yes' : 'No') : String(value).replaceAll('_', ' ')}</dd></div>)}</dl>;
}

export default function UserHistory({ subjectUser, events, offices }: Props) {
    return <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'Users', href: '/user-management' }, { title: 'History', href: `/user-management/${subjectUser.id}/history` }]}>
        <Head title="User history" /><div className="flex flex-col gap-6 p-4 md:p-6">
            <div><h1 className="text-2xl font-semibold">User history</h1><p className="mt-2 text-sm">{subjectUser.name} — {subjectUser.email}</p></div>
            <div><Link href="/user-management" className="inline-flex items-center gap-2 rounded border px-3 py-2 text-sm hover:bg-accent"><ArrowLeft aria-hidden="true" className="size-4" />Back to users</Link></div>
            {events.data.length === 0 ? <p>No recorded changes for this user.</p> : <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="p-2">Date and time (Sri Lanka)</th><th className="p-2">Action</th><th className="p-2">Changed by</th><th className="p-2">Previous values</th><th className="p-2">New values</th></tr></thead><tbody>{events.data.map(event => <tr key={event.id} className="border-b align-top"><td className="p-2">{formatSriLankaTime(event.created_at)}</td><td className="p-2">{event.action.replaceAll('_', ' ')}</td><td className="p-2">{event.actor_name ?? '—'}</td><td className="p-2"><ValuesList values={event.old_values} offices={offices} /></td><td className="p-2"><ValuesList values={event.new_values} offices={offices} /></td></tr>)}</tbody></table></div>}
            <nav aria-label="History pages" className="flex flex-wrap gap-2">{events.links.map((link, i) => link.url ? <Link key={i} href={link.url} aria-current={link.active ? 'page' : undefined} className="rounded border px-3 py-2 text-sm">{link.label.replace(/&laquo;|&raquo;/g, '').trim()}</Link> : <span key={i} className="rounded border px-3 py-2 text-sm opacity-50">{link.label.replace(/&laquo;|&raquo;/g, '').trim()}</span>)}</nav>
        </div>
    </AppLayout>;
}
