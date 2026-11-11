<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class DesignationPdfExportController extends Controller
{
    public function download(): Response
    {
        $designations = Designation::query()
            ->orderBy('code')
            ->get(['code', 'name', 'is_active']);

        $pdf = Pdf::loadView('pdf.designations', [
            'designations' => $designations,
        ]);

        return $pdf->download('designations.pdf');
    }
}