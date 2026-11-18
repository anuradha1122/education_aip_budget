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
            'offices' => $this->offices(),
            'result' => null,
        ]);
    }

    public function validateFile(Request $request): Response
    {
        $validated = $request->validate([
            'financial_year_id' => ['required', 'integer'],
            'office_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        $office = DB::table('offices')->where('id', $validated['office_id'])->first();

        if ($office === null) {
            throw ValidationException::withMessages(['office_id' => 'Select an existing office.']);
        }

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

                $voteCode = $cells[8];
                $fundCode = $cells[9];
                if ($voteCode !== '' && ! DB::table('votes')->where('code', $voteCode)->exists()) {
                    $errors[] = 'Vote code was not found in master data.';
                }
                if ($fundCode !== '' && ! DB::table('funds')->where('code', $fundCode)->exists()) {
                    $errors[] = 'Fund code was not found in master data.';
                }

                if ($cells[4] !== '' && ! DB::table('subjects')
                    ->where('office_id', $office->id)
                    ->where('code', $cells[4])
                    ->exists()) {
                    $errors[] = 'Subject code was not found for the selected office.';
                }

                $officerName = $cells[6];
                $designationName = $cells[7];
                if ($officerName !== '') {
                    $matches = DB::table('officers')
                        ->where('office_id', $office->id)
                        ->where('name', $officerName)
                        ->limit(2)
                        ->get(['id', 'designation_id']);
                    if ($matches->isEmpty()) {
                        $errors[] = 'Officer name was not found for the selected office.';
                    } elseif ($matches->count() > 1) {
                        $errors[] = 'Officer name matches more than one record in the selected office.';
                    } elseif ($designationName !== '' && ! DB::table('designations')
                        ->where('id', $matches[0]->designation_id)
                        ->where('name', $designationName)
                        ->exists()) {
                        $errors[] = 'Designation does not match the selected officer.';
                    }
                } elseif ($designationName !== '') {
                    $errors[] = 'Designation cannot be checked without an officer name.';
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

            $existing = [];
            foreach (array_chunk(array_keys($numberRows), 500) as $numbers) {
                $activities = DB::table('aip_activities as activity')
                    ->leftJoin('aip_activity_offices as link', function ($join) use ($office) {
                        $join->on('link.aip_activity_id', '=', 'activity.id')
                            ->where('link.office_id', '=', $office->id);
                    })
                    ->where('activity.financial_year_id', $year->id)
                    ->whereIn('activity.activity_number', $numbers)
                    ->get(['activity.activity_number', 'activity.description', 'link.id as office_link_id']);

                foreach ($activities as $activity) {
                    $existing['n'.(string) $activity->activity_number] = $activity;
                }
            }

            foreach ($rows as &$row) {
                $activity = $existing['n'.$row['activity_number']] ?? null;
                if ($activity !== null) {
                    $row['errors'][] = 'Activity already exists for this financial year; review its existing details before importing.';

                    if (trim((string) $activity->description) !== $row['activity']) {
                        $row['errors'][] = 'The activity description differs from the existing activity.';
                    }

                    if ($activity->office_link_id !== null) {
                        $row['errors'][] = 'This activity is already linked to the selected office.';
                    }
                }
            }
            unset($row);

            return Inertia::render('aip-activities/import', [
                'years' => $this->openYears(),
                'offices' => $this->offices(),
                'result' => [
                    'financial_year' => $year->year,
                    'office' => ['code' => $office->code, 'name' => $office->name],
                    'rows' => $rows,
                ],
            ]);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function offices()
    {
        return DB::table('offices')->orderBy('name')->get(['id', 'code', 'name', 'is_active']);
    }

    private function openYears()
    {
        return DB::table('financial_years')
            ->where('is_closed', false)
            ->orderByDesc('year')
            ->get(['id', 'year']);
    }
}
