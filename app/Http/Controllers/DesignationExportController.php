<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DesignationExportController extends Controller
{
    public function excel(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Designations');

                $sheet->setCellValue('A1', 'Designation code');
                $sheet->setCellValue('B1', 'Designation name');
                $sheet->setCellValue('C1', 'Status');

                $designations = Designation::query()
                    ->orderBy('code')
                    ->get(['code', 'name', 'is_active']);

                $row = 2;

                foreach ($designations as $designation) {
                    $sheet->setCellValueExplicit(
                        'A'.$row,
                        $designation->code,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'B'.$row,
                        $designation->name,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValue(
                        'C'.$row,
                        $designation->is_active ? 'Active' : 'Inactive'
                    );

                    $row++;
                }

                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'designations.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}