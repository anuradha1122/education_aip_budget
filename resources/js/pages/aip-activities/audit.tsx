import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Event = { id: number; current_activity_number: string; actor_name: string | null; action: string; old_values: string | null; new_values: string | null; created_at: string };
type PageLink = { url: string | null; label: string; active: boolean };
type Props = { events: { data: Event[]; links: PageLink[] } };

function displayValues(value: string | null): string {
    if (value === null) return 'Not recorded';
    try { return JSON.stringify(JSON.parse(value), null, 2); } catch { return value; }
}

export default function AipActivityAudit({ events }: Props) {
    return <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'AIP activities', href: '/aip-activities' }, { title: 'Audit history', href: '/aip-activities/audit' }]}>
        <Head title="AIP activity audit history" />
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <div><h1 className="text-2xl font-semibold">AIP activity audit history</h1><p className="mt-2 text-sm text-muted-foreground">Recorded activity changes.</p><Link href="/aip-activities" className="mt-3 inline-block underline">Back to AIP activities</Link></div>
            {events.data.length === 0 ? <p className="rounded border p-4">No audit events have been recorded.</p> : events.data.map((event) => <article key={event.id} className="rounded border p-4">
                <div className="grid gap-2 text-sm md:grid-cols-2"><p><strong>Current activity number:</strong> {event.current_activity_number}</p><p><strong>Action:</strong> {event.action}</p><p><strong>User:</strong> {event.actor_name ?? 'Unavailable'}</p><p><strong>Recorded time:</strong> {event.created_at}</p></div>
                <div className="mt-4 grid gap-4 md:grid-cols-2"><div><h2 className="mb-2 font-medium">Previous values</h2><pre className="overflow-x-auto whitespace-pre-wrap break-words rounded border p-3 text-xs">{displayValues(event.old_values)}</pre></div><div><h2 className="mb-2 font-medium">New values</h2><pre className="overflow-x-auto whitespace-pre-wrap break-words rounded border p-3 text-xs">{displayValues(event.new_values)}</pre></div></div>
            </article>)}
            <nav aria-label="Audit history pages" className="flex flex-wrap gap-2">{events.links.map((link, i) => { const label = link.label.replace(/&laquo;|&raquo;/g, '').trim(); return link.url ? <Link key={i} href={link.url} aria-current={link.active ? 'page' : undefined} className="rounded border px-3 py-2 text-sm">{label}</Link> : <span key={i} className="rounded border px-3 py-2 text-sm opacity-50">{label}</span>; })}</nav>
        </div>
    </AppLayout>;
}
