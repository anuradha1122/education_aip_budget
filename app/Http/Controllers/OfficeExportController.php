<?php

namespace App\Http\Controllers;

use App\Models\Office;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class OfficeExportController extends Controller
{
    public function excel(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Offices');

                $headers = [
                    'A1' => 'Office code',
                    'B1' => 'Office name',
                    'C1' => 'Office type',
                    'D1' => 'Status',
                ];

                foreach ($headers as $cell => $value) {
                    $sheet->setCellValueExplicit(
                        $cell,
                        $value,
                        DataType::TYPE_STRING
                    );
                }

                $sheet->getStyle('A1:D1')->getFont()->setBold(true);
                $sheet->freezePane('A2');

                $sheet->getColumnDimension('A')->setWidth(22);
                $sheet->getColumnDimension('B')->setWidth(50);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(15);

                $row = 2;

                foreach (Office::query()->orderBy('code')->cursor() as $office) {
                    $values = [
                        'A' => $office->code,
                        'B' => $office->name,
                        'C' => $office->type,
                        'D' => $office->is_active ? 'Active' : 'Inactive',
                    ];

                    foreach ($values as $column => $value) {
                        $sheet->setCellValueExplicit(
                            $column.$row,
                            (string) $value,
                            DataType::TYPE_STRING
                        );
                    }

                    $row++;
                }

                $sheet->setAutoFilter('A1:D'.max(1, $row - 1));

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'offices.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function pdf(): Response
    {
        $offices = Office::query()
            ->orderBy('code')
            ->get(['code', 'name', 'type', 'is_active']);

        return Pdf::loadView('pdf.offices', [
            'offices' => $offices,
        ])
            ->setPaper('a4')
            ->download('offices.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    public function importTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet();

            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Offices');

                $headers = [
                    'A1' => 'Office code',
                    'B1' => 'Office name',
                    'C1' => 'Office type',
                    'D1' => 'Status',
                ];

                foreach ($headers as $cell => $value) {
                    $sheet->setCellValueExplicit(
                        $cell,
                        $value,
                        DataType::TYPE_STRING
                    );
                }

                $sheet->getStyle('A1:D1')->getFont()->setBold(true);
                $sheet->freezePane('A2');

                $sheet->getColumnDimension('A')->setWidth(22);
                $sheet->getColumnDimension('B')->setWidth(50);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(15);

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'office-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
