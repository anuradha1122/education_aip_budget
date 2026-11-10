<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubjectImportTemplateController extends Controller
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Subjects');

                $sheet->setCellValue('A1', 'Office code');
                $sheet->setCellValue('B1', 'Subject code');
                $sheet->setCellValue('C1', 'Subject name');
                $sheet->setCellValue('D1', 'Status');

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'subject-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}