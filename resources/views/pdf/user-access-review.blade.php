<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>User access review</title>
<style>
    @page { margin: 24px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #202020; }
    h1 { font-size: 17px; margin-bottom: 4px; }
    p { margin: 5px 0; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 12px; }
    th, td { border: 1px solid #aaa; padding: 5px; text-align: left; overflow-wrap: break-word; vertical-align: top; }
    th { background: #eee; } tr { page-break-inside: avoid; }
</style></head>
<body>
<h1>User access review</h1>
<p>Search: {{ $search !== '' ? $search : 'All users' }} | Generated: {{ now()->format('Y-m-d H:i') }}</p>
<p>Recorded roles do not grant all planned permissions. Only selected current permissions are shown.</p>
<table><thead><tr><th>User</th><th>Status</th><th>Recorded role</th><th>Office</th><th>Linked officer</th><th>Administrator</th><th>Import flag</th><th>AIP register</th><th>AIP import</th><th>Other permissions</th></tr></thead>
<tbody>@forelse($rows as $row)
<tr><td>{{ $row->name }}<br>{{ $row->email }}</td><td>{{ $row->is_active ? 'Active' : 'Inactive' }}</td><td>{{ $row->role ? str_replace('_', ' ', $row->role) : '—' }}</td><td>{{ $row->office_name ? $row->office_code.' — '.$row->office_name.($row->office_active ? '' : ' (inactive)') : '—' }}</td><td>{{ $row->officer_name ? $row->officer_name.($row->officer_active ? '' : ' (inactive)') : '—' }}</td><td>{{ $row->is_admin ? 'Yes' : 'No' }}</td><td>{{ $row->can_import_aip ? 'Yes' : 'No' }}</td><td>{{ $row->register_allowed ? 'Allowed' : 'Not allowed' }}</td><td>{{ $row->import_allowed ? 'Allowed' : 'Not allowed' }}</td><td>Financial years: {{ $row->financial_years_allowed ? 'Allowed' : 'Not allowed' }}<br>Organisational data: {{ $row->organisational_data_allowed ? 'Allowed' : 'Not allowed' }}<br>AIP assignments: {{ $row->aip_assignments_allowed ? 'Allowed' : 'Not allowed' }}</td></tr>
@empty<tr><td colspan="10">No users found.</td></tr>@endforelse</tbody></table>
</body></html>
