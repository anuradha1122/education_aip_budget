<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FundExportController extends Controller
{
    public function excel(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Funds');

                $sheet->fromArray([
                    ['Fund code', 'Fund name', 'Status'],
                ]);

                $sheet->getStyle('A1:C1')->getFont()->setBold(true);

                $funds = Fund::query()
                    ->orderBy('name')
                    ->get(['code', 'name', 'is_active']);

                $row = 2;

                foreach ($funds as $fund) {
                    // Store text explicitly so spreadsheet applications
                    // do not interpret fund values as formulas.
                    $sheet->setCellValueExplicit(
                        'A'.$row,
                        $fund->code,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'B'.$row,
                        $fund->name,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'C'.$row,
                        $fund->is_active ? 'Active' : 'Inactive',
                        DataType::TYPE_STRING
                    );

                    $row++;
                }

                foreach (['A', 'B', 'C'] as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $sheet->freezePane('A2');

                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'fund-list.xlsx', [
            'Content-Type' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
