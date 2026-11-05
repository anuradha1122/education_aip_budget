<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundImportTemplateController extends Controller
{
    public function download(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Funds');

                $headers = [
                    'A1' => 'Fund code',
                    'B1' => 'Fund name',
                    'C1' => 'Status',
                ];

                foreach ($headers as $cell => $value) {
                    $sheet->setCellValueExplicit(
                        $cell,
                        $value,
                        DataType::TYPE_STRING
                    );
                }

                $sheet->getStyle('A1:C1')->getFont()->setBold(true);
                $sheet->freezePane('A2');

                $sheet->getColumnDimension('A')->setWidth(22);
                $sheet->getColumnDimension('B')->setWidth(50);
                $sheet->getColumnDimension('C')->setWidth(18);

                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'fund-import-template.xlsx', [
            'Content-Type' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
