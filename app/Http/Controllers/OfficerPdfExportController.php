<?php

namespace App\Http\Controllers;

use App\Models\Officer;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class OfficerPdfExportController extends Controller
{
    public function download(): Response
    {
        $officers = Officer::query()
            ->with([
                'office:id,code,name',
                'designation:id,code,name',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('pdf.officers', [
            'officers' => $officers,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('officers.pdf');
    }
}