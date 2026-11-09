<?php

namespace App\Http\Controllers;

use App\Models\Vote;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class VotePdfExportController extends Controller
{
    public function download(): Response
    {
        $votes = Vote::query()
            ->orderBy('code')
            ->get(['code', 'description', 'is_active']);

        $pdf = Pdf::loadView('pdf.votes', [
            'votes' => $votes,
        ]);

        return $pdf->download('votes.pdf');
    }
}