<?php

namespace App\Http\Controllers;

use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OfficerSubjectAssignmentController extends Controller
{
    public function index(): Response
    {
        $officers = DB::table('officers')->join('offices', 'offices.id', '=', 'officers.office_id')
            ->select('officers.id', 'officers.name', 'officers.office_id', 'officers.is_active', 'offices.name as office_name')
            ->orderBy('offices.name')->orderBy('officers.name')->get();
        $subjects = DB::table('subjects')->join('offices', 'offices.id', '=', 'subjects.office_id')
            ->select('subjects.id', 'subjects.code', 'subjects.name', 'subjects.office_id', 'subjects.is_active', 'offices.name as office_name')
            ->orderBy('offices.name')->orderBy('subjects.name')->get();
        $assignments = DB::table('officer_subject_assignments as assignments')
            ->join('officers', 'officers.id', '=', 'assignments.officer_id')
            ->join('subjects', 'subjects.id', '=', 'assignments.subject_id')
            ->join('offices', 'offices.id', '=', 'officers.office_id')
            ->select('assignments.id', 'assignments.ended_at', 'officers.name as officer_name', 'subjects.name as subject_name',
                'subjects.code as subject_code', 'offices.name as officer_office',
                'subjects.office_id as subject_office_id', 'officers.office_id as officer_office_id')
            ->orderByDesc('assignments.id')->paginate(20);

        return Inertia::render('officer-subject-assignments/index', compact('officers', 'subjects', 'assignments'));
    }

    public function store(Request $request, OfficerSubjectAssignmentService $service): RedirectResponse
    {
        $service->assign($request->only('officer_id', 'subject_id'), $request->user());

        return redirect()->route('officer-subject-assignments.index');
    }
    public function end(int $id, Request $request, OfficerSubjectAssignmentService $service): RedirectResponse
    {
        $service->end($id, $request->user());

        return redirect()->route('officer-subject-assignments.index');
    }

}
