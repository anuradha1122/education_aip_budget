<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(): Response
    {
        return $this->renderPage();
    }

    public function edit(Subject $subject): Response
    {
        return $this->renderPage($subject);
    }

    public function store(
        Request $request,
        SubjectService $service
    ): RedirectResponse {
        $service->create(
            $request->only(['office_id', 'code', 'name', 'is_active']),
            $request->user()
        );

        return redirect()->route('subjects.index');
    }

    public function update(
        Request $request,
        Subject $subject,
        SubjectService $service
    ): RedirectResponse {
        $service->update(
            $subject,
            $request->only(['office_id', 'code', 'name', 'is_active']),
            $request->user()
        );

        return redirect()->route('subjects.index');
    }

    private function renderPage(?Subject $editingSubject = null): Response
    {
        $subjects = Subject::query()
            ->with('office:id,code,name')
            ->orderBy('code')
            ->get();

        return Inertia::render('subjects/index', [
            'subjects' => $subjects,
            'offices' => Office::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
            'editingSubject' => $editingSubject,
        ]);
    }
}