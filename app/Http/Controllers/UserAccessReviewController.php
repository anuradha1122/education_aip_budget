<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserAccessReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['search'] ?? '');
        $users = DB::table('users as u')
            ->leftJoin('user_access_assignments as a', 'a.user_id', '=', 'u.id')
            ->leftJoin('offices as o', 'o.id', '=', 'a.office_id')
            ->leftJoin('user_officer_links as l', 'l.user_id', '=', 'u.id')
            ->leftJoin('officers as officer', 'officer.id', '=', 'l.officer_id')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('u.name', 'like', '%'.$search.'%')->orWhere('u.email', 'like', '%'.$search.'%')->orWhere('o.name', 'like', '%'.$search.'%')->orWhere('o.code', 'like', '%'.$search.'%')))
            ->orderBy('u.name')->orderBy('u.id')
            ->paginate(20, ['u.id', 'u.name', 'u.email', 'u.is_active', 'u.is_admin', 'u.can_import_aip',
                'a.role', 'o.code as office_code', 'o.name as office_name', 'o.is_active as office_active',
                'officer.name as officer_name', 'officer.is_active as officer_active'])
            ->withQueryString();

        // Use the same gates as the application, without exposing credentials.
        $accounts = User::query()->whereIn('id', $users->getCollection()->pluck('id'))
            ->get(['id', 'is_admin', 'is_active', 'can_import_aip'])->keyBy('id');
        $users->getCollection()->transform(function ($row) use ($accounts) {
            $user = $accounts->get($row->id);
            $active = (bool) $row->is_active;
            $row->is_active = $active;
            $row->is_admin = (bool) $row->is_admin;
            $row->can_import_aip = (bool) $row->can_import_aip;
            $row->register_allowed = $active && Gate::forUser($user)->allows('view-aip-register');
            $row->import_allowed = $active && Gate::forUser($user)->allows('import-aip');
            return $row;
        });

        return Inertia::render('user-management/access-review', compact('users', 'search'));
    }
}
