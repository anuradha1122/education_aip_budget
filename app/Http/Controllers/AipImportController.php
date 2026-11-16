<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class AipImportController extends Controller
{
    private const HEADERS = [
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

    public function index(): Response
    {
        return Inertia::render('aip-activities/import', [
            'years' => $this->openYears(),
            'result' => null,
        ]);
    }

    public function validateFile(Request $request): Response
    {
        $validated = $request->validate([
            'financial_year_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        $year = DB::table('financial_years')
            ->where('id', $validated['financial_year_id'])
            ->first();

        if ($year === null || (bool) $year->is_closed) {
            throw ValidationException::withMessages([
                'financial_year_id' => 'Select an existing open financial year.',
            ]);
        }

        try {
            $reader = IOFactory::createReaderForFile(
                $request->file('file')->getRealPath()
            );
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load(
                $request->file('file')->getRealPath()
            );
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be read as an Excel workbook.',
            ]);
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();

            $headers = [];
            foreach (range('A', 'J') as $column) {
                $headers[] = trim((string) $sheet->getCell($column.'1')->getValue());
            }

            if ($headers !== self::HEADERS) {
                throw ValidationException::withMessages([
                    'file' => 'The column headings do not match the AIP template.',
                ]);
            }

            $highestRow = $sheet->getHighestDataRow();

            if ($highestRow > 5001) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook may contain at most 5,000 data rows.',
                ]);
            }

            $rows = [];
            $numberRows = [];

            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $cells = [];

                foreach (range('A', 'J') as $column) {
                    $cells[] = trim(
                        (string) $sheet->getCell($column.$rowNumber)->getValue()
                    );
                }

                if (count(array_filter($cells, fn ($value) => $value !== '')) === 0) {
                    continue;
                }

                $number = $cells[2];
                $errors = [];

                if ($number === '' || ! preg_match('/\A[0-9]+\z/', $number)) {
                    $errors[] = 'Activity number must contain digits only.';
                } elseif (strlen($number) > 50) {
                    $errors[] = 'Activity number must not exceed 50 characters.';
                } else {
                    $numberRows[$number][] = count($rows);
                }

                if ($cells[3] === '') {
                    $errors[] = 'Activity description is required.';
                }

                $rows[] = [
                    'row_number' => $rowNumber,
                    'serial_number' => $cells[0],
                    'level' => $cells[1],
                    'activity_number' => $number,
                    'activity' => $cells[3],
                    'subject_code' => $cells[4],
                    'subject' => $cells[5],
                    'officer' => $cells[6],
                    'designation' => $cells[7],
                    'vote' => $cells[8],
                    'fund' => $cells[9],
                    'errors' => $errors,
                ];
            }

            if (count($rows) === 0) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook does not contain any activity rows.',
                ]);
            }

            foreach ($numberRows as $number => $indexes) {
                if (count($indexes) > 1) {
                    foreach ($indexes as $index) {
                        $rows[$index]['errors'][] =
                            'Activity number is repeated in this workbook.';
                    }
                }
            }

            $existingNumbers = DB::table('aip_activities')
                ->where('financial_year_id', $year->id)
                ->whereIn('activity_number', array_keys($numberRows))
                ->pluck('activity_number')
                ->map(fn ($number) => (string) $number)
                ->all();

            foreach ($rows as &$row) {
                if (in_array($row['activity_number'], $existingNumbers, true)) {
                    $row['errors'][] =
                        'Activity number already exists for this financial year.';
                }
            }
            unset($row);

            return Inertia::render('aip-activities/import', [
                'years' => $this->openYears(),
                'result' => [
                    'financial_year' => $year->year,
                    'rows' => $rows,
                ],
            ]);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function openYears()
    {
        return DB::table('financial_years')
            ->where('is_closed', false)
            ->orderByDesc('year')
            ->get(['id', 'year']);
    }
}