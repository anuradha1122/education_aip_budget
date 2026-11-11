<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Services\DesignationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $designations = Designation::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_active']);

        return Inertia::render('designations/index', [
            'designations' => $designations,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(
        Request $request,
        DesignationService $service
    ): RedirectResponse {
        $service->create($request->only([
            'code',
            'name',
            'is_active',
        ]), $request->user());

        return redirect()->route('designations.index');
    }

    public function update(
        Request $request,
        Designation $designation,
        DesignationService $service
    ): RedirectResponse {
        $service->update($designation, $request->only([
            'code',
            'name',
            'is_active',
        ]), $request->user());

        return redirect()->route('designations.index');
    }
}