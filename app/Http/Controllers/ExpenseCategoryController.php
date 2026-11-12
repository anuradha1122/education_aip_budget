<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $categories = ExpenseCategory::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'is_active']);

        return Inertia::render('expense-categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(
        Request $request,
        ExpenseCategoryService $service
    ): RedirectResponse {
        $service->create($request->only([
            'code',
            'name',
            'is_active',
        ]), $request->user());

        return redirect()->route('expense-categories.index');
    }

    public function update(
        Request $request,
        ExpenseCategory $expenseCategory,
        ExpenseCategoryService $service
    ): RedirectResponse {
        $service->update($expenseCategory, $request->only([
            'code',
            'name',
            'is_active',
        ]), $request->user());

        return redirect()->route('expense-categories.index');
    }
}