<?php

namespace App\Http\Controllers;

use App\Models\AipActivity;
use App\Services\AipActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AipActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $isMainAdmin = (bool) $request->user()->is_admin;
        $officeId = null;
        $officerId = null;
        $provinceWide = $isMainAdmin;
        if (! $isMainAdmin) {
            $scope = app(\App\Services\AipRegisterScope::class)->forUser($request->user());
            abort_if($scope === null, 403);
            $provinceWide = $scope->role === 'provincial_admin';
            $officeId = $scope->office_id;
            $officerId = $scope->role === 'subject_director' ? $scope->officer_id : null;
        }

        $activities = AipActivity::query()
            ->with($isMainAdmin
                ? ['financialYear:id,year', 'subject:id,code,name,office_id', 'officer:id,name,office_id']
                : ['financialYear:id,year'])
            ->when(! $provinceWide, fn ($query) => $query->whereExists(function ($query) use ($officeId, $officerId) {
                $query->selectRaw('1')->from('aip_activity_offices as scoped_link')
                    ->whereColumn('scoped_link.aip_activity_id', 'aip_activities.id')
                    ->where('scoped_link.office_id', $officeId)
                    ->when($officerId !== null, fn ($query) => $query->where('scoped_link.officer_id', $officerId));
            }))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('activity_number', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('id')->paginate(20)->withQueryString();

        $activityIds = $activities->getCollection()->pluck('id')->all();
        $officeLinks = DB::table('aip_activity_offices as link')
            ->join('offices as office', 'office.id', '=', 'link.office_id')
            ->leftJoin('subjects as subject', 'subject.id', '=', 'link.subject_id')
            ->leftJoin('officers as officer', 'officer.id', '=', 'link.officer_id')
            ->whereIn('link.aip_activity_id', $activityIds)
            ->when(! $provinceWide, fn ($query) => $query->where('link.office_id', $officeId))
            ->when($officerId !== null, fn ($query) => $query->where('link.officer_id', $officerId))
            ->orderBy('office.name')->orderBy('link.id')
            ->get([
                'link.id', 'link.aip_activity_id', 'office.code as office_code',
                'office.name as office_name', 'subject.code as subject_code',
                'subject.name as subject_name', 'officer.name as officer_name',
            ])->groupBy('aip_activity_id');
        $funding = DB::table('aip_activity_funding_entries as entry')
            ->join('funds as fund', 'fund.id', '=', 'entry.fund_id')
            ->join('votes as vote', 'vote.id', '=', 'entry.vote_id')
            ->whereIn('entry.aip_activity_id', $activityIds)
            ->get([
                'entry.aip_activity_id', 'fund.code as fund_code', 'fund.name as fund_name',
                'vote.code as vote_code', 'vote.description as vote_description',
            ])->keyBy('aip_activity_id');
        $activities->getCollection()->each(function ($activity) use ($officeLinks, $funding, $isMainAdmin) {
            if (! $isMainAdmin) {
                $activity->makeHidden(['subject_id', 'officer_id']);
                $activity->setRelation('subject', null);
                $activity->setRelation('officer', null);
            }
            $activity->setAttribute('office_assignments', $officeLinks->get($activity->id, collect())->values()->all());
            $activity->setAttribute('funding', $funding->get($activity->id));
        });

        $canCreateAip = $request->user()->can('create-aip-activity');
        if (! $canCreateAip) {
            return Inertia::render('aip-activities/index', [
                'activities' => $activities, 'search' => $search,
                'years' => [], 'officers' => [], 'assignments' => [], 'defaultFinancialYearId' => null,
                'canImportAip' => false, 'canManageAip' => false, 'canManageAipAssignments' => false, 'canCreateAip' => false,
            ]);
        }

        $years = DB::table('financial_years')->where('is_closed', false)
            ->orderByDesc('year')->get(['id', 'year']);
        $currentYear = now('Asia/Colombo')->year;
        $currentOpenYear = $years->first(fn ($year) => (int) $year->year === $currentYear);
        $defaultFinancialYearId = $currentOpenYear ? (int) $currentOpenYear->id : null;
        $officers = DB::table('officers')->join('offices', 'offices.id', '=', 'officers.office_id')
            ->orderBy('offices.name')->orderBy('officers.name')
            ->get(['officers.id', 'officers.name', 'officers.office_id', 'officers.is_active', 'offices.name as office_name']);
        $assignments = DB::table('officer_subject_assignments as a')
            ->join('officers as o', 'o.id', '=', 'a.officer_id')
            ->join('subjects as s', 's.id', '=', 'a.subject_id')
            ->whereNull('a.ended_at')->whereColumn('o.office_id', 's.office_id')
            ->orderBy('s.code')->orderBy('s.name')
            ->get(['a.officer_id', 'a.subject_id', 's.code as subject_code', 's.name as subject_name', 's.is_active as subject_active']);

        return Inertia::render('aip-activities/index', [
            ...compact('activities', 'years', 'officers', 'assignments', 'search', 'defaultFinancialYearId'),
            'canImportAip' => $request->user()->can('import-aip'),
            'canManageAip' => $isMainAdmin,
            'canManageAipAssignments' => $request->user()->can('manage-aip-assignments'),
            'canCreateAip' => $canCreateAip,
        ]);
    }

    public function store(Request $request, AipActivityService $service): RedirectResponse
    {
        $service->create($request->only([
            'financial_year_id', 'activity_number', 'description', 'level', 'location', 'subject_id', 'officer_id',
        ]), $request->user());

        return redirect()->route('aip-activities.index');
    }
}
