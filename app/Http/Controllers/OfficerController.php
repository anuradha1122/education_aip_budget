<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Services\OfficerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $officers = Officer::query()
            ->with([
                'office:id,code,name',
                'designation:id,code,name',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('officers/index', [
            'officers' => $officers,
            'offices' => Office::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
            'designations' => Designation::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(
        Request $request,
        OfficerService $service
    ): RedirectResponse {
        $service->create($request->only([
            'office_id',
            'designation_id',
            'name',
            'is_active',
        ]), $request->user());

        return redirect()->route('officers.index');
    }

    public function update(
        Request $request,
        Officer $officer,
        OfficerService $service
    ): RedirectResponse {
        $service->update($officer, $request->only([
            'office_id',
            'designation_id',
            'name',
            'is_active',
        ]), $request->user());

        return redirect()->route('officers.index');
    }
}