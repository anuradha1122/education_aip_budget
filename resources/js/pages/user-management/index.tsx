import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { ClipboardList, History, Pencil, Plus, Unlink, UserCheck, UserRoundX } from 'lucide-react';

type Officer = { id: number; name: string; office_id: number };
type Office = { id: number; code: string; name: string };
type User = { id: number; name: string; email: string; is_admin: boolean | number; is_active: boolean; can_import_aip: boolean; role: string | null; office_id: number | null; officer_id: number | null; officer_name: string | null; office_name: string | null };
type Page = { data: User[]; links: { url: string | null; label: string; active: boolean }[] };
type Props = { offices: Office[]; officers: Officer[]; users: Page; search: string; managementScope: string; managementOfficeId: number | null };

export default function UserManagement({ offices, officers, users, search, managementScope, managementOfficeId }: Props) {
    const zonal = managementScope === 'zonal_admin';
    const { data, setData, post, processing, errors, reset, recentlySuccessful } = useForm({
        name: '', email: '', password: '', password_confirmation: '', role: zonal ? 'subject_director' : '', office_id: zonal ? String(managementOfficeId) : '',
    });
    const [searchText, setSearchText] = useState(search);
    const [editing, setEditing] = useState<User | null>(null);
    const [linking, setLinking] = useState<User | null>(null);
    const officerLink = useForm({ officer_id: '' });
    const unlink = useForm({});
    const [unlinkError, setUnlinkError] = useState('');
    const [editingDetails, setEditingDetails] = useState<User | null>(null);
    const details = useForm({ name: '', email: '', password: '', password_confirmation: '' });
    const [creating, setCreating] = useState(false);
    const status = useForm({ is_active: true });
    const importPermission = useForm({ can_import_aip: false });
    function changeImportPermission(user: User) {
        const allowed = !user.can_import_aip;
        if (!window.confirm(`${allowed ? 'Allow' : 'Revoke'} AIP import for ${user.name}?`)) return;
        importPermission.transform(() => ({ can_import_aip: allowed }));
        importPermission.patch(`/user-management/${user.id}/import-permission`, { preserveScroll: true });
    }
    const access = useForm({ role: '', office_id: '' });
    function removeOfficer(user: User) {
        if (!window.confirm(`Remove the officer link for ${user.name}? They will lose subject-director AIP access.`)) return;
        setUnlinkError('');
        unlink.delete(`/user-management/${user.id}/officer`, {
            preserveScroll: true,
            onError: errors => setUnlinkError(Object.values(errors)[0] ?? 'Unable to remove the officer link.'),
        });
    }
    function startLinking(user: User) {
        setLinking(user);
        officerLink.clearErrors();
        officerLink.setData({ officer_id: user.officer_id === null ? '' : String(user.officer_id) });
    }
    function saveOfficer(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (linking) officerLink.put(`/user-management/${linking.id}/officer`, { onSuccess: () => setLinking(null) });
    }
    function startEditingDetails(user: User) {
        setEditingDetails(user);
        details.clearErrors();
        details.setData({ name: user.name, email: user.email, password: '', password_confirmation: '' });
    }
    function saveDetails(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (editingDetails) details.put(`/user-management/${editingDetails.id}/details`, {
            onSuccess: () => { details.reset(); setEditingDetails(null); },
            onFinish: () => details.reset('password', 'password_confirmation'),
        });
    }
    function startEditing(user: User) {
        setEditing(user);
        access.clearErrors();
        access.setData({ role: user.role ?? '', office_id: user.office_id === null ? '' : String(user.office_id) });
    }
    function changeStatus(user: User) {
        const active = !user.is_active;
        if (!window.confirm(`${active ? 'Activate' : 'Deactivate'} ${user.name}?`)) return;
        status.transform(() => ({ is_active: active }));
        status.patch(`/user-management/${user.id}/status`, { preserveScroll: true });
    }
    function saveAccess(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (editing) access.put(`/user-management/${editing.id}/access`, { onSuccess: () => setEditing(null) });
    }
    function searchUsers(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/user-management', { search: searchText }, { preserveState: false });
    }
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/user-management', { onSuccess: () => { reset(); setCreating(false); }, onFinish: () => reset('password', 'password_confirmation') });
    }
    return <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'Users', href: '/user-management' }]}>
        <Head title="Users" />
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <div><h1 className="text-2xl font-semibold">Users</h1>
                <p className="mt-2 text-sm">{zonal ? 'Manage subject directors in your office.' : 'Manage user accounts and assignments.'}</p>
            </div>
            {creating && <form onSubmit={submit} className="flex w-full flex-col gap-4 rounded border p-4">
                <h2 className="text-lg font-semibold">Create user</h2>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="min-w-0">
                    <label>Name<input required maxLength={255} value={data.name} onChange={e => setData('name', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>
                    {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                    </div>
                    <div className="min-w-0">
                    <label>Email<input required type="email" maxLength={255} value={data.email} onChange={e => setData('email', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>
                    {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
                    </div>
                    <div className="min-w-0">
                    <label>Password (minimum 12 characters)<input required type="password" minLength={12} autoComplete="new-password" value={data.password} onChange={e => setData('password', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>
                    {errors.password && <p className="text-sm text-red-600">{errors.password}</p>}
                    </div>
                    <div className="min-w-0">
                    <label>Confirm password<input required type="password" minLength={12} autoComplete="new-password" value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>
                    </div>
                    <div className="min-w-0">
                    <label>Role<select required disabled={zonal} value={data.role} onChange={e => setData({ ...data, role: e.target.value, office_id: '' })} className="mt-1 block w-full rounded border bg-background p-2"><option value="">Select role</option>{!zonal && <><option value="provincial_admin">Provincial administrator</option><option value="zonal_admin">Zonal administrator</option></>}<option value="subject_director">Subject director</option></select></label>
                    {errors.role && <p className="text-sm text-red-600">{errors.role}</p>}
                    </div>
                    <div className="min-w-0">
                    {data.role !== 'provincial_admin' && <label>Office<select required disabled={zonal} value={data.office_id} onChange={e => setData('office_id', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2"><option value="">Select office</option>{offices.map(office => <option key={office.id} value={office.id}>{office.code} — {office.name}</option>)}</select></label>}
                    {errors.office_id && <p className="text-sm text-red-600">{errors.office_id}</p>}
                    </div>
                </div>
                <div className="flex gap-3"><button disabled={processing} className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50">{processing ? 'Creating...' : 'Create user'}</button><button type="button" onClick={() => { reset(); setCreating(false); }} className="rounded border px-4 py-2">Cancel</button></div>
                {recentlySuccessful && <p role="status" className="text-sm">User created.</p>}
            </form>}
            {linking && <form onSubmit={saveOfficer} className="flex w-full flex-col gap-4 rounded border p-4">
                <h2 className="text-lg font-semibold">Link officer: {linking.name}</h2>
                <p className="text-sm">Select an active officer from this user’s office. The link determines which AIP activities a subject director can view.</p>
                <label>Officer<select required value={officerLink.data.officer_id} onChange={e => officerLink.setData('officer_id', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2"><option value="">Select an active officer</option>{officers.filter(o => String(o.office_id) === String(linking.office_id)).map(o => <option key={o.id} value={o.id}>{o.name} (ID: {o.id})</option>)}</select></label>
                {officerLink.errors.officer_id && <p className="text-sm text-red-600">{officerLink.errors.officer_id}</p>}
                <div className="flex gap-3"><button type="submit" disabled={officerLink.processing} className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50">Save officer</button><button type="button" onClick={() => setLinking(null)} className="rounded border px-4 py-2">Cancel</button></div>
            </form>}
            {editingDetails && <form onSubmit={saveDetails} className="flex w-full flex-col gap-4 rounded border p-4">
                <h2 className="text-lg font-semibold">Edit user: {editingDetails.name}</h2>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label>Name<input required maxLength={255} value={details.data.name} onChange={e => details.setData('name', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>{details.errors.name && <p className="text-sm text-red-600">{details.errors.name}</p>}</div>
                    <div><label>Email<input required type="email" maxLength={255} value={details.data.email} onChange={e => details.setData('email', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>{details.errors.email && <p className="text-sm text-red-600">{details.errors.email}</p>}</div>
                    <div><label>New password (optional, at least 12 characters)<input type="password" minLength={12} autoComplete="new-password" value={details.data.password} onChange={e => details.setData('password', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label>{details.errors.password && <p className="text-sm text-red-600">{details.errors.password}</p>}</div>
                    <div><label>Confirm new password<input type="password" autoComplete="new-password" value={details.data.password_confirmation} onChange={e => details.setData('password_confirmation', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2" /></label></div>
                </div>
                <p className="text-sm text-muted-foreground">Changing the email or password signs the user out on their next request.</p>
                <div className="flex gap-3"><button type="submit" disabled={details.processing} className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50">{details.processing ? 'Saving...' : 'Save user'}</button><button type="button" onClick={() => { details.reset(); setEditingDetails(null); }} className="rounded border px-4 py-2">Cancel</button></div>
            </form>}
            {editing && <form onSubmit={saveAccess} className="flex w-full flex-col gap-4 rounded border p-4">
                <h2 className="text-lg font-semibold">Edit recorded access: {editing.name}</h2>
                <p className="text-sm">Changing the role or office removes any linked officer and may remove AIP import permission.</p>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label>Role<select required value={access.data.role} onChange={e => access.setData({ role: e.target.value, office_id: '' })} className="mt-1 block w-full rounded border bg-background p-2"><option value="">Select role</option><option value="provincial_admin">Provincial administrator</option><option value="zonal_admin">Zonal administrator</option><option value="subject_director">Subject director</option></select></label>{access.errors.role && <p className="text-sm text-red-600">{access.errors.role}</p>}</div>
                    {access.data.role !== 'provincial_admin' && <div><label>Office<select required value={access.data.office_id} onChange={e => access.setData('office_id', e.target.value)} className="mt-1 block w-full rounded border bg-background p-2"><option value="">Select active office</option>{offices.map(office => <option key={office.id} value={office.id}>{office.code} — {office.name}</option>)}</select></label>{access.errors.office_id && <p className="text-sm text-red-600">{access.errors.office_id}</p>}</div>}
                </div>
                <div className="flex gap-3"><button type="submit" disabled={access.processing} className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50">{access.processing ? 'Saving...' : 'Save access'}</button><button type="button" onClick={() => setEditing(null)} className="rounded border px-4 py-2">Cancel</button></div>
            </form>}
            {!creating && !editing && !editingDetails && !linking && <section><div className="flex flex-wrap items-center justify-between gap-4"><h2 className="text-lg font-semibold">User accounts</h2><Link href="/user-management/access-review" className="inline-flex items-center gap-2 rounded border px-4 py-2 hover:bg-accent"><ClipboardList aria-hidden="true" className="size-4" />Access review</Link><button type="button" onClick={() => setCreating(true)} className="inline-flex items-center gap-2 rounded bg-primary px-4 py-2 text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><Plus aria-hidden="true" className="size-4" />Add user</button></div><form onSubmit={searchUsers} className="mt-4 flex flex-wrap gap-2"><label><span className="sr-only">Search users by name or email</span><input value={searchText} onChange={e => setSearchText(e.target.value)} maxLength={100} placeholder="Search name or email" className="rounded border bg-background p-2" /></label><button type="submit" className="rounded border px-3 py-2 hover:bg-accent">Search</button><Link href="/user-management" className="rounded border px-3 py-2 hover:bg-accent">Clear</Link></form>{unlinkError && <p role="alert" className="mt-2 text-sm text-red-600">{unlinkError}</p>}{importPermission.errors.can_import_aip && <p role="alert" className="mt-2 text-sm text-red-600">{importPermission.errors.can_import_aip}</p>}{status.errors.is_active && <p role="alert" className="mt-2 text-sm text-red-600">{status.errors.is_active}</p>}<div className="mt-3 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="p-2">Name</th><th className="p-2">Email</th><th className="p-2">Recorded role</th><th className="p-2">Office</th><th className="p-2">Linked officer</th><th className="p-2">Existing administrator</th><th className="p-2">Status</th><th className="p-2">Action</th></tr></thead><tbody>{users.data.map(user => <tr key={user.id} className="border-b"><td className="p-2">{user.name}</td><td className="p-2">{user.email}</td><td className="p-2">{user.role?.replaceAll('_', ' ') ?? '—'}</td><td className="p-2">{user.office_name ?? '—'}</td><td className="p-2">{user.officer_name ?? '—'}</td><td className="p-2">{user.is_admin ? 'Yes' : 'No'}</td><td className="p-2">{user.is_active ? 'Active' : 'Inactive'}</td><td className="p-2"><div className="flex flex-wrap gap-2"><Link href={`/user-management/${user.id}/history`} className="inline-flex items-center gap-2 whitespace-nowrap rounded border px-3 py-1.5 text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><History aria-hidden="true" className="size-4" />History</Link>{managementScope === 'system_admin' && (Boolean(user.is_admin) || (user.role === 'provincial_admin' && user.office_id === null) || user.can_import_aip) && <button type="button" disabled={importPermission.processing || (!user.is_active && !user.can_import_aip)} onClick={() => changeImportPermission(user)} className="inline-flex items-center gap-2 whitespace-nowrap rounded border px-3 py-1.5 text-sm hover:bg-accent disabled:opacity-50"><Pencil aria-hidden="true" className="size-4" />{user.can_import_aip ? 'Revoke AIP import' : 'Allow AIP import'}</button>}{!user.is_admin && <>{user.officer_id !== null && <button type="button" disabled={unlink.processing} onClick={() => removeOfficer(user)} className="inline-flex items-center gap-2 whitespace-nowrap rounded border px-3 py-1.5 text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"><Unlink aria-hidden="true" className="size-4" />Remove officer</button>}{user.role === 'subject_director' && <button type="button" onClick={() => startLinking(user)} className="inline-flex items-center gap-2 whitespace-nowrap rounded border px-3 py-1.5 text-sm hover:bg-accent"><Pencil aria-hidden="true" className="size-4" />Link officer</button>}<button type="button" className="inline-flex items-center gap-2 whitespace-nowrap rounded border px-3 py-1.5 text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onClick={() => startEditingDetails(user)}><Pencil aria-hidden="true" className="size-4" />Edit user</button>{!zonal && <button type="button" className="inline-flex items-center gap-2 whitespace-nowrap rounded border px-3 py-1.5 text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onClick={() => startEditing(user)}><Pencil aria-hidden="true" className="size-4" />Edit access</button>}<button type="button" disabled={status.processing} onClick={() => changeStatus(user)} className="inline-flex items-center gap-2 whitespace-nowrap rounded border px-3 py-1.5 text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50">{user.is_active ? <UserRoundX aria-hidden="true" className="size-4" /> : <UserCheck aria-hidden="true" className="size-4" />}{user.is_active ? 'Deactivate' : 'Activate'}</button></>}</div></td></tr>)}</tbody></table></div>
                <nav aria-label="User pages" className="mt-4 flex gap-2">{users.links.map((link, i) => link.url ? <Link key={i} href={link.url} aria-current={link.active ? 'page' : undefined} className="rounded border px-3 py-2">{link.label.replace(/&laquo;|&raquo;/g, '').trim()}</Link> : <span key={i} className="rounded border px-3 py-2 opacity-50">{link.label.replace(/&laquo;|&raquo;/g, '').trim()}</span>)}</nav>
            </section>}
        </div>
    </AppLayout>;
}
