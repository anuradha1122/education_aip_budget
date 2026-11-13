import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Year = { id: number; year: number };
type Officer = { id: number; name: string; office_name: string; is_active: number | boolean };
type Assignment = { officer_id: number; subject_id: number; subject_code: string; subject_name: string; subject_active: number | boolean };
type Activity = { id: number; activity_number: string; description: string; location: string | null; financial_year: { year: number }; subject: { code: string; name: string } | null; officer: { name: string } | null };
type PageLink = { url: string | null; label: string; active: boolean };
type Props = { years: Year[]; officers: Officer[]; assignments: Assignment[]; search: string; activities: { data: Activity[]; links: PageLink[] } };

export default function AipActivities({ years, officers, assignments, search, activities }: Props) {
    const [searchText, setSearchText] = useState(search);
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        financial_year_id: '', activity_number: '', description: '', location: '', officer_id: '', subject_id: '',
    });
    const available = assignments.filter((assignment) => String(assignment.officer_id) === data.officer_id);
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/aip-activities');
    }
    function searchActivities(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/aip-activities', { search: searchText }, { preserveState: true });
    }
    return <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'AIP activities', href: '/aip-activities' }]}>
        <Head title="AIP activities" />
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 className="text-2xl font-semibold">AIP activities</h1><p className="mt-2 text-sm text-muted-foreground">Create and review activities for a financial year.</p>
            
                <a href="/aip-activities/audit" className="mt-3 inline-block underline">
                    View audit history
                </a>

                <Link
                    href="/aip-activity-offices"
                    className="inline-block underline"
                >
                    Manage activity offices
                </Link>
            </div>
            <form onSubmit={submit} className="flex max-w-3xl flex-col gap-4 rounded border p-4">
                <h2 className="text-lg font-semibold">Add activity</h2>
                <label>Financial year<select required value={data.financial_year_id} onChange={(event) => setData('financial_year_id', event.target.value)} className="mt-1 block w-full rounded border bg-background p-2"><option value="">Select an open year</option>{years.map((year) => <option key={year.id} value={year.id}>{year.year}</option>)}</select></label>
                {years.length === 0 && <p className="text-sm">No open financial years are available.</p>}
                {errors.financial_year_id && <p className="text-sm text-red-600">{errors.financial_year_id}</p>}
                <label>Activity number<input required maxLength={50} value={data.activity_number} onChange={(event) => setData('activity_number', event.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>
                {errors.activity_number && <p className="text-sm text-red-600">{errors.activity_number}</p>}
                <label>Activity description<textarea required rows={3} value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>
                <p className="text-sm text-muted-foreground">Enter the AIP description in English.</p>
                {errors.description && <p className="text-sm text-red-600">{errors.description}</p>}
                <label>Level / location (optional)<input maxLength={255} value={data.location} onChange={(event) => setData('location', event.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>
                {errors.location && <p className="text-sm text-red-600">{errors.location}</p>}
                <p className="text-sm text-muted-foreground">Officer and subject may both be left blank for now. If selected, choose an assigned pair.</p>
                <label>Officer<select value={data.officer_id} onChange={(event) => setData({ ...data, officer_id: event.target.value, subject_id: '' })} className="mt-1 block w-full rounded border bg-background p-2"><option value="">Not selected</option>{officers.map((officer) => <option key={officer.id} value={officer.id}>{officer.name} — {officer.office_name}{Number(officer.is_active) === 0 ? ' (inactive)' : ''}</option>)}</select></label>
                {errors.officer_id && <p className="text-sm text-red-600">{errors.officer_id}</p>}
                <label>Assigned subject<select value={data.subject_id} disabled={!data.officer_id} onChange={(event) => setData('subject_id', event.target.value)} className="mt-1 block w-full rounded border bg-background p-2"><option value="">Not selected</option>{available.map((item) => <option key={item.subject_id} value={item.subject_id}>{item.subject_code} — {item.subject_name}{Number(item.subject_active) === 0 ? ' (inactive)' : ''}</option>)}</select></label>
                {data.officer_id && available.length === 0 && <p className="text-sm">No current assignments are available for this officer.</p>}
                {errors.subject_id && <p className="text-sm text-red-600">{errors.subject_id}</p>}
                <div><button type="submit" disabled={processing || years.length === 0} className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50">{processing ? 'Saving...' : 'Create activity'}</button></div>
                {recentlySuccessful && <p role="status" className="text-sm">Activity saved.</p>}
            </form>
            <section><h2 className="text-lg font-semibold">Activity register</h2>
                <form onSubmit={searchActivities} className="mt-3 flex flex-wrap gap-2"><label><span className="sr-only">Search activities</span><input value={searchText} onChange={(event) => setSearchText(event.target.value)} placeholder="Search number or description" className="rounded border bg-background p-2" /></label><button type="submit" className="rounded border px-3 py-2">Search</button><Link href="/aip-activities" onClick={() => setSearchText('')} className="rounded border px-3 py-2">Clear</Link></form>
                {activities.data.length === 0 ? <p className="mt-3">No activities found.</p> : <div className="mt-3 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="p-2">Year</th><th className="p-2">Number</th><th className="p-2">Description</th><th className="p-2">Level</th><th className="p-2">Subject</th><th className="p-2">Officer</th></tr></thead><tbody>{activities.data.map((activity) => <tr className="border-b" key={activity.id}><td className="p-2">{activity.financial_year.year}</td><td className="p-2">{activity.activity_number}</td><td className="p-2">{activity.description}</td><td className="p-2">{activity.location ?? '—'}</td><td className="p-2">{activity.subject ? `${activity.subject.code} — ${activity.subject.name}` : '—'}</td><td className="p-2">{activity.officer?.name ?? '—'}</td></tr>)}</tbody></table></div>}
                <nav aria-label="Activity pages" className="mt-4 flex flex-wrap gap-2">{activities.links.map((link, i) => { const label = link.label.replace(/&laquo;|&raquo;/g, '').trim(); return link.url ? <Link key={i} href={link.url} aria-current={link.active ? 'page' : undefined} className="rounded border px-3 py-2 text-sm">{label}</Link> : <span key={i} className="rounded border px-3 py-2 text-sm opacity-50">{label}</span>; })}</nav>
            </section>
        </div>
    </AppLayout>;
}
