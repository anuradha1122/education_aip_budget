<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class FundPdfExportController extends Controller
{
    public function download(): Response
    {
        $funds = Fund::query()
            ->orderBy('name')
            ->get(['code', 'name', 'is_active']);

        return Pdf::loadView('pdf.funds', [
            'funds' => $funds,
        ])
            ->setPaper('a4')
            ->download('fund-list.pdf')
            ->header('Cache-Control', 'private, no-store');
    }
}
