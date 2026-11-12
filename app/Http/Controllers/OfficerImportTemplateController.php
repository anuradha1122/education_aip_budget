<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficerImportTemplateController extends Controller
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Officers');

                $sheet->setCellValue('A1', 'Officer name');
                $sheet->setCellValue('B1', 'Office code');
                $sheet->setCellValue('C1', 'Designation code');
                $sheet->setCellValue('D1', 'Status');

                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'officer-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}