<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AipImportTemplateController extends Controller
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('AIP');

                $headers = [
                    'Serial number',
                    'Level',
                    'Activity Number',
                    'Activity',
                    'Subject code',
                    'Subject',
                    'Officer',
                    'Designation',
                    'Vote',
                    'Fund',
                ];

                $sheet->fromArray($headers, null, 'A1');
                $sheet->getStyle('A1:J1')->getFont()->setBold(true);

                foreach (range('A', 'J') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'aip-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}