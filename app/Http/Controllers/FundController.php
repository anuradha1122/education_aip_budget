<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use App\Services\FundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $funds = Fund::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active']);

        return Inertia::render('funds/index', [
            'funds' => $funds,
            'search' => $search,
            'editingFund' => null,
        ]);
    }

    public function edit(Fund $fund): Response
    {
        return Inertia::render('funds/index', [
            'funds' => Fund::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
            'search' => '',
            'editingFund' => $fund->only([
                'id',
                'code',
                'name',
                'is_active',
            ]),
        ]);
    }

    public function store(
        Request $request,
        FundService $fundService
    ): RedirectResponse {
        $fundService->create(
            $request->only(['code', 'name', 'is_active']),
            $request->user()
        );

        return redirect()->route('funds.index');
    }

    public function update(
        Request $request,
        Fund $fund,
        FundService $fundService
    ): RedirectResponse {
        $fundService->update(
            $fund,
            $request->only(['code', 'name', 'is_active']),
            $request->user()
        );

        return redirect()->route('funds.index');
    }
}
