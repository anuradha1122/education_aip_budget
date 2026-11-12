<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgrammeTypeImportTemplateController extends Controller
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Programme types');

                $sheet->setCellValue('A1', 'Programme type code');
                $sheet->setCellValue('B1', 'Programme type name');
                $sheet->setCellValue('C1', 'Status');

                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'programme-type-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}