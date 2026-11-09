<?php

namespace App\Http\Controllers;

use App\Models\Vote;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoteExportController extends Controller
{
    public function excel(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Votes');

                $sheet->setCellValue('A1', 'Vote code');
                $sheet->setCellValue('B1', 'Vote description');
                $sheet->setCellValue('C1', 'Status');

                $row = 2;

                foreach (Vote::query()->orderBy('code')->cursor() as $vote) {
                    // Explicit text prevents spreadsheet formula execution.
                    $sheet->setCellValueExplicit(
                        'A'.$row,
                        $vote->code,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValueExplicit(
                        'B'.$row,
                        $vote->description,
                        DataType::TYPE_STRING
                    );

                    $sheet->setCellValue(
                        'C'.$row,
                        $vote->is_active ? 'Active' : 'Inactive'
                    );

                    $row++;
                }

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'votes.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}