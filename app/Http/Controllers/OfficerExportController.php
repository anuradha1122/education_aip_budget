<?php

namespace App\Http\Controllers;

use App\Models\Officer;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficerExportController extends Controller
{
    public function excel(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Officers');

                $headers = [
                    'Officer name',
                    'Office code',
                    'Office name',
                    'Designation code',
                    'Designation name',
                    'Status',
                ];

                $sheet->fromArray($headers, null, 'A1');

                $officers = Officer::query()
                    ->with([
                        'office:id,code,name',
                        'designation:id,code,name',
                    ])
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get();

                $row = 2;

                foreach ($officers as $officer) {
                    $sheet->setCellValueExplicit(
                        'A'.$row,
                        $officer->name,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'B'.$row,
                        $officer->office->code,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'C'.$row,
                        $officer->office->name,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'D'.$row,
                        $officer->designation->code,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'E'.$row,
                        $officer->designation->name,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValue(
                        'F'.$row,
                        $officer->is_active ? 'Active' : 'Inactive'
                    );

                    $row++;
                }

                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'officers.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}