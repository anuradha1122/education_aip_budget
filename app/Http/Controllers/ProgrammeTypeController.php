<?php

namespace App\Http\Controllers;

use App\Models\ProgrammeType;
use App\Services\ProgrammeTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgrammeTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $programmeTypes = ProgrammeType::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_active']);

        return Inertia::render('programme-types/index', [
            'programmeTypes' => $programmeTypes,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(
        Request $request,
        ProgrammeTypeService $service
    ): RedirectResponse {
        $service->create($request->only([
            'code',
            'name',
            'is_active',
        ]), $request->user());

        return redirect()->route('programme-types.index');
    }

    public function update(
        Request $request,
        ProgrammeType $programmeType,
        ProgrammeTypeService $service
    ): RedirectResponse {
        $service->update($programmeType, $request->only([
            'code',
            'name',
            'is_active',
        ]), $request->user());

        return redirect()->route('programme-types.index');
    }
}