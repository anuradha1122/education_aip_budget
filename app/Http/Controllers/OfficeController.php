<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Services\OfficeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $offices = Office::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'is_active']);

        return Inertia::render('offices/index', [
            'offices' => $offices,
            'search' => $search,
            'editingOffice' => null,
        ]);
    }

    public function edit(Office $office): Response
    {
        return Inertia::render('offices/index', [
            'offices' => Office::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type', 'is_active']),
            'search' => '',
            'editingOffice' => $office->only([
                'id',
                'code',
                'name',
                'type',
                'is_active',
            ]),
        ]);
    }

    public function store(
        Request $request,
        OfficeService $officeService
    ): RedirectResponse {
        $officeService->create($request->all());

        return redirect()->route('offices.index');
    }

    public function update(
        Request $request,
        Office $office,
        OfficeService $officeService
    ): RedirectResponse {
        $officeService->update($office, $request->all());

        return redirect()->route('offices.index');
    }
}
