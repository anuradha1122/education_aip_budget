<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class OfficerImportController extends Controller
{
    private const EXPECTED_HEADERS = [
        'Officer name',
        'Office code',
        'Designation code',
        'Status',
    ];

    private const MAX_DATA_ROWS = 500;

    public function index(): Response
    {
        return Inertia::render('officers/import', [
            'result' => null,
        ]);
    }

    public function validateFile(Request $request): Response
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
        ]);

        $path = $request->file('file')->getRealPath();

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);

            $sheetInfo = $reader->listWorksheetInfo($path);

            if (count($sheetInfo) !== 1) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook must contain exactly one worksheet.',
                ]);
            }

            $totalRows = $sheetInfo[0]['totalRows'] ?? 0;
            $totalColumns = $sheetInfo[0]['totalColumns'] ?? 0;

            if ($totalRows > self::MAX_DATA_ROWS + 1) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook must not contain more than 500 data rows.',
                ]);
            }

            if ($totalColumns > 4) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook must contain only the four template columns.',
                ]);
            }

            $spreadsheet = $reader->load($path);
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be read as an Excel workbook.',
            ]);
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $headers = [];

            foreach (['A1', 'B1', 'C1', 'D1'] as $address) {
                $cell = $sheet->getCell($address);

                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw ValidationException::withMessages([
                        'file' => 'The template headings cannot contain formulas.',
                    ]);
                }

                $headers[] = trim((string) $cell->getValue());
            }

            if ($headers !== self::EXPECTED_HEADERS) {
                throw ValidationException::withMessages([
                    'file' => 'The headings do not match the officer import template.',
                ]);
            }

            $offices = [];

            foreach (Office::query()->get(['id', 'code']) as $office) {
                $offices[mb_strtoupper(trim($office->code))] = $office->id;
            }

            $designations = [];

            foreach (Designation::query()->get(['id', 'code']) as $designation) {
                $designations[mb_strtoupper(trim($designation->code))]
                    = $designation->id;
            }

            $existing = [];

            foreach (Officer::query()->get([
                'name',
                'office_id',
                'designation_id',
            ]) as $officer) {
                $nameKey = mb_strtolower(trim($officer->name));

                $existing[$officer->office_id]
                    [$officer->designation_id]
                    [$nameKey] = true;
            }

            $seen = [];
            $rows = [];

            for ($rowNumber = 2; $rowNumber <= $totalRows; $rowNumber++) {
                $cells = [
                    'name' => $sheet->getCell('A'.$rowNumber),
                    'office_code' => $sheet->getCell('B'.$rowNumber),
                    'designation_code' => $sheet->getCell('C'.$rowNumber),
                    'status' => $sheet->getCell('D'.$rowNumber),
                ];

                $hasFormula = false;

                foreach ($cells as $cell) {
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $hasFormula = true;
                    }
                }

                $name = trim((string) $cells['name']->getValue());

                $officeCode = mb_strtoupper(
                    trim((string) $cells['office_code']->getValue())
                );

                $designationCode = mb_strtoupper(
                    trim((string) $cells['designation_code']->getValue())
                );

                $status = strtolower(
                    trim((string) $cells['status']->getValue())
                );

                if (
                    $name === ''
                    && $officeCode === ''
                    && $designationCode === ''
                    && $status === ''
                ) {
                    continue;
                }

                $validator = Validator::make([
                    'name' => $name,
                    'office_code' => $officeCode,
                    'designation_code' => $designationCode,
                    'status' => $status,
                ], [
                    'name' => [
                        'required',
                        'string',
                        'max:255',
                        'not_regex:/^[\p{N}\s]+$/u',
                    ],
                    'office_code' => ['required', 'string'],
                    'designation_code' => ['required', 'string'],
                    'status' => [
                        'required',
                        Rule::in(['active', 'inactive']),
                    ],
                ]);

                $errors = $validator->errors()->all();
                $warnings = [];

                if ($hasFormula) {
                    $errors[] = 'Formulas are not allowed in import rows.';
                }

                $officeId = $offices[$officeCode] ?? null;
                $designationId = $designations[$designationCode] ?? null;

                if ($officeCode !== '' && $officeId === null) {
                    $errors[] = 'The office code does not exist in the system.';
                }

                if ($designationCode !== '' && $designationId === null) {
                    $errors[] = 'The designation code does not exist in the system.';
                }

                if ($name !== '' && $officeId !== null && $designationId !== null) {
                    $nameKey = mb_strtolower($name);

                    if (isset($existing[$officeId][$designationId][$nameKey])) {
                        $warnings[] = 'An officer with the same name, office and designation already exists. Check whether this is a different person.';
                    }

                    if (isset($seen[$officeId][$designationId][$nameKey])) {
                        $warnings[] = 'Another row has the same name, office and designation. Check whether these are different people.';
                    }

                    $seen[$officeId][$designationId][$nameKey] = true;
                }

                $rows[] = [
                    'row' => $rowNumber,
                    'name' => $name,
                    'office_code' => $officeCode,
                    'designation_code' => $designationCode,
                    'status' => $status,
                    'errors' => $errors,
                    'warnings' => $warnings,
                ];
            }

            if (count($rows) === 0) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook does not contain any officer data.',
                ]);
            }

            $invalidCount = count(array_filter(
                $rows,
                fn ($row) => count($row['errors']) > 0
            ));

            $warningCount = count(array_filter(
                $rows,
                fn ($row) => count($row['warnings']) > 0
            ));

            return Inertia::render('officers/import', [
                'result' => [
                    'rows' => $rows,
                    'total' => count($rows),
                    'invalid' => $invalidCount,
                    'warnings' => $warningCount,
                ],
            ]);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }
}