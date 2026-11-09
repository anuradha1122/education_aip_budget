<?php

namespace App\Http\Controllers;

use App\Models\Vote;
use App\Services\VoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoteController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $votes = Vote::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('code')
            ->get(['id', 'code', 'description', 'is_active']);

        return Inertia::render('votes/index', [
            'votes' => $votes,
            'search' => $search,
            'editingVote' => null,
        ]);
    }

    public function edit(Vote $vote): Response
    {
        return Inertia::render('votes/index', [
            'votes' => Vote::query()
                ->orderBy('code')
                ->get(['id', 'code', 'description', 'is_active']),
            'search' => '',
            'editingVote' => $vote->only([
                'id',
                'code',
                'description',
                'is_active',
            ]),
        ]);
    }

    public function store(
        Request $request,
        VoteService $voteService
    ): RedirectResponse {
        $voteService->create(
            $request->only(['code', 'description', 'is_active']),
            $request->user()
        );

        return redirect()->route('votes.index');
    }

    public function update(
        Request $request,
        Vote $vote,
        VoteService $voteService
    ): RedirectResponse {
        $voteService->update(
            $vote,
            $request->only(['code', 'description', 'is_active']),
            $request->user()
        );

        return redirect()->route('votes.index');
    }
}