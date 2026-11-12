<?php

namespace App\Http\Controllers;

use App\Models\ProgrammeType;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ProgrammeTypePdfExportController extends Controller
{
    public function download(): Response
    {
        $programmeTypes = ProgrammeType::query()
            ->orderBy('code')
            ->get(['code', 'name', 'is_active']);

        $pdf = Pdf::loadView('pdf.programme-types', [
            'programmeTypes' => $programmeTypes,
        ]);

        return $pdf->download('programme-types.pdf');
    }
}