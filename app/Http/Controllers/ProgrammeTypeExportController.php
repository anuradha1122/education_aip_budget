<?php

namespace App\Http\Controllers;

use App\Models\ProgrammeType;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgrammeTypeExportController extends Controller
{
    public function excel(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Programme types');

                $sheet->setCellValue('A1', 'Programme type code');
                $sheet->setCellValue('B1', 'Programme type name');
                $sheet->setCellValue('C1', 'Status');

                $programmeTypes = ProgrammeType::query()
                    ->orderBy('code')
                    ->get(['code', 'name', 'is_active']);

                $row = 2;

                foreach ($programmeTypes as $programmeType) {
                    $sheet->setCellValueExplicit(
                        'A'.$row,
                        $programmeType->code,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'B'.$row,
                        $programmeType->name,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValue(
                        'C'.$row,
                        $programmeType->is_active ? 'Active' : 'Inactive'
                    );

                    $row++;
                }

                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'programme-types.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}