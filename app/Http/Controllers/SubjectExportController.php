<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubjectExportController extends Controller
{
    public function excel(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Subjects');

                $sheet->setCellValue('A1', 'Office code');
                $sheet->setCellValue('B1', 'Office name');
                $sheet->setCellValue('C1', 'Subject code');
                $sheet->setCellValue('D1', 'Subject name');
                $sheet->setCellValue('E1', 'Status');

                $subjects = Subject::query()
                    ->with('office:id,code,name')
                    ->orderBy('office_id')
                    ->orderBy('code')
                    ->get();

                $row = 2;

                foreach ($subjects as $subject) {
                    $sheet->setCellValueExplicit(
                        'A'.$row,
                        $subject->office->code,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'B'.$row,
                        $subject->office->name,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'C'.$row,
                        $subject->code,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'D'.$row,
                        $subject->name,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValue(
                        'E'.$row,
                        $subject->is_active ? 'Active' : 'Inactive'
                    );

                    $row++;
                }

                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'subjects.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}