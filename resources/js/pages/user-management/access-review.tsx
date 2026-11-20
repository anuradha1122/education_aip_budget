import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, Search } from 'lucide-react';
import { type FormEvent, useState } from 'react';

type Entry = { id: number; name: string; email: string; is_active: boolean; is_admin: boolean; can_import_aip: boolean; role: string | null; office_code: string | null; office_name: string | null; office_active: boolean | number | null; officer_name: string | null; officer_active: boolean | number | null; register_allowed: boolean; import_allowed: boolean };
type Props = { users: { data: Entry[]; links: { url: string | null; label: string; active: boolean }[] }; search: string };

export default function AccessReview({ users, search }: Props) {
    const [query, setQuery] = useState(search);
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/user-management/access-review', { search: query });
    }
    return <AppLayout breadcrumbs={[{ title: 'Users', href: '/user-management' }, { title: 'Access review', href: '/user-management/access-review' }]}>
        <Head title="Access review" />
        <div className="flex flex-col gap-5 p-4 md:p-6">
            <div><h1 className="text-2xl font-semibold">Access review</h1>
                <p className="mt-2 text-sm text-muted-foreground">Review current account assignments and selected AIP permissions. Recorded roles do not grant all planned permissions.</p>
                <p className="mt-1 text-sm text-muted-foreground">AIP access reflects account status and current register and import gates. Other permissions are not shown.</p></div>
            <div className="flex flex-wrap gap-2"><a href={`/user-management/access-review/export/excel${search ? `?search=${encodeURIComponent(search)}` : ''}`} className="inline-flex items-center gap-2 rounded border px-3 py-2 hover:bg-accent"><Download aria-hidden="true" className="size-4" />Export Excel</a><a href={`/user-management/access-review/export/pdf${search ? `?search=${encodeURIComponent(search)}` : ''}`} className="inline-flex items-center gap-2 rounded border px-3 py-2 hover:bg-accent"><Download aria-hidden="true" className="size-4" />Export PDF</a><Link href="/user-management" className="inline-flex items-center gap-2 rounded border px-3 py-2 hover:bg-accent"><ArrowLeft aria-hidden="true" className="size-4" />Back to users</Link></div>
            <form onSubmit={submit} className="flex flex-wrap gap-2"><label><span className="sr-only">Search name, email, office or code</span><input value={query} onChange={e => setQuery(e.target.value)} maxLength={100} placeholder="Search name, email, office or code" className="rounded border bg-background p-2" /></label><button className="inline-flex items-center gap-2 rounded border px-3 py-2 hover:bg-accent"><Search aria-hidden="true" className="size-4" />Search</button><Link href="/user-management/access-review" className="rounded border px-3 py-2 hover:bg-accent">Clear</Link></form>
            <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="p-2">User</th><th className="p-2">Status</th><th className="p-2">Recorded role</th><th className="p-2">Office</th><th className="p-2">Linked officer</th><th className="p-2">Administrator</th><th className="p-2">Import flag</th><th className="p-2">AIP register</th><th className="p-2">AIP import</th></tr></thead><tbody>
                {users.data.map(u => <tr key={u.id} className="border-b"><td className="p-2">{u.name}<br /><span className="text-muted-foreground">{u.email}</span></td><td className="p-2">{u.is_active ? 'Active' : 'Inactive'}</td><td className="p-2">{u.role?.replaceAll('_', ' ') ?? '—'}</td><td className="p-2">{u.office_name ? `${u.office_code} — ${u.office_name}${u.office_active ? '' : ' (inactive)'}` : '—'}</td><td className="p-2">{u.officer_name ? `${u.officer_name}${u.officer_active ? '' : ' (inactive)'}` : '—'}</td><td className="p-2">{u.is_admin ? 'Yes' : 'No'}</td><td className="p-2">{u.can_import_aip ? 'Yes' : 'No'}</td><td className="p-2">{u.register_allowed ? 'Allowed' : 'Not allowed'}</td><td className="p-2">{u.import_allowed ? 'Allowed' : 'Not allowed'}</td></tr>)}
                {users.data.length === 0 && <tr><td colSpan={9} className="p-4">No users found.</td></tr>}
            </tbody></table></div>
            <nav aria-label="Access review pages" className="flex flex-wrap gap-2">{users.links.map((link, index) => link.url ? <Link key={index} href={link.url} aria-current={link.active ? 'page' : undefined} className={`rounded border px-3 py-2 ${link.active ? 'font-bold' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={index} className="rounded border px-3 py-2 opacity-50" dangerouslySetInnerHTML={{ __html: link.label }} />)}</nav>
        </div>
    </AppLayout>;
}
