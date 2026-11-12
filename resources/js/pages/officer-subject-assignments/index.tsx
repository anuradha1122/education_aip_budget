import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Officer = { id: number; name: string; office_id: number; office_name: string; is_active: number | boolean };
type Subject = { id: number; code: string; name: string; office_id: number; office_name: string; is_active: number | boolean };
type Assignment = { id: number; officer_name: string; subject_name: string; subject_code: string; officer_office: string; officer_office_id: number; subject_office_id: number; ended_at: string | null };
type PageLink = { url: string | null; label: string; active: boolean };
type Props = { officers: Officer[]; subjects: Subject[]; assignments: { data: Assignment[]; links: PageLink[] } };

export default function OfficerSubjectAssignments({ officers, subjects, assignments }: Props) {
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({ officer_id: '', subject_id: '' });
    const [endError, setEndError] = useState('');
    const [endingId, setEndingId] = useState<number | null>(null);
    function endAssignment(id: number) {
        setEndError('');
        router.patch(`/officer-subject-assignments/${id}/end`, {}, {
            preserveScroll: true,
            onStart: () => setEndingId(id),
            onFinish: () => setEndingId(null),
            onError: (errors) => setEndError(errors.assignment || 'The assignment could not be ended.'),
        });
    }
    const selectedOfficer = officers.find((officer) => String(officer.id) === data.officer_id);
    const availableSubjects = selectedOfficer ? subjects.filter((subject) => String(subject.office_id) === String(selectedOfficer.office_id)) : [];
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/officer-subject-assignments');
    }
    return <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }, { title: 'Officer subject assignments', href: '/officer-subject-assignments' }]}>
        <Head title="Officer subject assignments" />
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <div><h1 className="text-2xl font-semibold">Officer subject assignments</h1><p className="mt-2 text-sm text-muted-foreground">Assign an officer to a subject in the same office.</p></div>
            <form onSubmit={submit} className="flex max-w-2xl flex-col gap-4 rounded border p-4">
                <label>Officer<select className="mt-1 block w-full rounded border bg-background p-2" value={data.officer_id} required onChange={(event) => { setData({ officer_id: event.target.value, subject_id: '' }); }}>
                    <option value="">Select an officer</option>{officers.map((officer) => <option key={officer.id} value={officer.id}>{officer.name} — {officer.office_name}{Number(officer.is_active) === 0 ? ' (inactive)' : ''}</option>)}
                </select></label>
                {errors.officer_id && <p className="text-sm text-red-600">{errors.officer_id}</p>}
                <label>Subject<select className="mt-1 block w-full rounded border bg-background p-2" value={data.subject_id} required disabled={!selectedOfficer} onChange={(event) => setData('subject_id', event.target.value)}>
                    <option value="">Select a subject</option>{availableSubjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.code} — {subject.name}{Number(subject.is_active) === 0 ? ' (inactive)' : ''}</option>)}
                </select></label>
                {errors.subject_id && <p className="text-sm text-red-600">{errors.subject_id}</p>}
                {selectedOfficer && availableSubjects.length === 0 && <p className="text-sm">No subjects are available for this office.</p>}
                <div><button type="submit" disabled={processing} className="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50">{processing ? 'Saving...' : 'Assign subject'}</button></div>
                {recentlySuccessful && <p role="status" className="text-sm">Assignment saved.</p>}
            </form>
            <section><h2 className="text-lg font-semibold">Assignments</h2>{endError && <p role="alert" className="mt-2 text-sm text-red-600">{endError}</p>}
                {assignments.data.length === 0 ? <p className="mt-2">No assignments have been recorded.</p> : <div className="mt-3 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="p-2">Officer current office</th><th className="p-2">Officer</th><th className="p-2">Subject</th><th className="p-2">Status</th><th className="p-2">Action</th></tr></thead><tbody>
                    {assignments.data.map((assignment) => <tr className="border-b" key={assignment.id}><td className="p-2">{assignment.officer_office}</td><td className="p-2">{assignment.officer_name}</td><td className="p-2">{assignment.subject_code} — {assignment.subject_name}{!assignment.ended_at && String(assignment.officer_office_id) !== String(assignment.subject_office_id) && <span className="block text-red-600">Office mismatch — review assignment</span>}</td><td className="p-2">{assignment.ended_at ? `Ended: ${assignment.ended_at}` : 'Current'}</td><td className="p-2">{!assignment.ended_at && <button type="button" className="underline disabled:opacity-50" disabled={endingId !== null} onClick={() => endAssignment(assignment.id)}>{endingId === assignment.id ? 'Ending...' : 'End assignment'}</button>}</td></tr>)}
                </tbody></table></div>}
                <nav aria-label="Assignment pages" className="mt-4 flex flex-wrap gap-2">{assignments.links.map((link, i) => { const label = link.label.replace(/&laquo;|&raquo;/g, '').trim(); return link.url ? <Link key={i} href={link.url} aria-current={link.active ? 'page' : undefined} className="rounded border px-3 py-2 text-sm">{label}</Link> : <span key={i} className="rounded border px-3 py-2 text-sm opacity-50">{label}</span>; })}</nav>
            </section>
        </div>
    </AppLayout>;
}
