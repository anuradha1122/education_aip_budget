<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class SubjectPdfExportController extends Controller
{
    public function download(): Response
    {
        $subjects = Subject::query()
            ->with('office:id,code,name')
            ->orderBy('office_id')
            ->orderBy('code')
            ->get();

        $pdf = Pdf::loadView('pdf.subjects', [
            'subjects' => $subjects,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('subjects.pdf');
    }
}