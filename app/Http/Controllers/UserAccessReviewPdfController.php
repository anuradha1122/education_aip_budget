<?php

namespace App\Http\Controllers;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class UserAccessReviewPdfController extends Controller
{
    public function download(Request $request): Response
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['search'] ?? '');
        $rows = DB::table('users as u')
            ->leftJoin('user_access_assignments as a', 'a.user_id', '=', 'u.id')
            ->leftJoin('offices as o', 'o.id', '=', 'a.office_id')
            ->leftJoin('user_officer_links as l', 'l.user_id', '=', 'u.id')
            ->leftJoin('officers as officer', 'officer.id', '=', 'l.officer_id')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('u.name', 'like', '%'.$search.'%')->orWhere('u.email', 'like', '%'.$search.'%')->orWhere('o.name', 'like', '%'.$search.'%')->orWhere('o.code', 'like', '%'.$search.'%')))
            ->orderBy('u.name')->orderBy('u.id')
            ->get(['u.id', 'u.name', 'u.email', 'u.is_active', 'u.is_admin', 'u.can_import_aip', 'a.role', 'o.code as office_code', 'o.name as office_name', 'o.is_active as office_active', 'officer.name as officer_name', 'officer.is_active as officer_active']);
        $accounts = User::query()->whereIn('id', $rows->pluck('id'))->get(['id', 'is_admin', 'is_active', 'can_import_aip'])->keyBy('id');
        foreach ($rows as $row) {
            $account = $accounts->get($row->id);
            $row->register_allowed = (bool) $row->is_active && Gate::forUser($account)->allows('view-aip-register');
            $row->import_allowed = (bool) $row->is_active && Gate::forUser($account)->allows('import-aip');
            $row->financial_years_allowed = (bool) $row->is_active && Gate::forUser($account)->allows('manage-financial-years');
            $row->organisational_data_allowed = (bool) $row->is_active && Gate::forUser($account)->allows('manage-organisational-data');
            $row->aip_assignments_allowed = (bool) $row->is_active && Gate::forUser($account)->allows('manage-aip-assignments');
        }
        return Pdf::loadView('pdf.user-access-review', compact('rows', 'search'))
            ->setPaper('a4', 'landscape')
            ->download('user-access-review.pdf')
            ->header('Cache-Control', 'private, no-store');
    }
}
