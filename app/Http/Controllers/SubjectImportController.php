<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class SubjectImportController extends Controller
{
    private const EXPECTED_HEADERS = [
        'Office code',
        'Subject code',
        'Subject name',
        'Status',
    ];

    private const MAX_DATA_ROWS = 500;

    public function index(): Response
    {
        return Inertia::render('subjects/import', [
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
                    'file' => 'The headings do not match the subject import template.',
                ]);
            }

            $offices = [];

            foreach (Office::query()->get(['id', 'code']) as $office) {
                $officeCode = mb_strtoupper(trim($office->code));
                $offices[$officeCode] = $office->id;
            }

            $existingCodes = [];

            foreach (Subject::query()->get(['office_id', 'code']) as $subject) {
                $code = mb_strtoupper(trim($subject->code));

                $existingCodes[$subject->office_id][$code] = true;
            }

            $seenCodes = [];
            $rows = [];

            for ($rowNumber = 2; $rowNumber <= $totalRows; $rowNumber++) {
                $cells = [
                    'office_code' => $sheet->getCell('A'.$rowNumber),
                    'code' => $sheet->getCell('B'.$rowNumber),
                    'name' => $sheet->getCell('C'.$rowNumber),
                    'status' => $sheet->getCell('D'.$rowNumber),
                ];

                $hasFormula = false;

                foreach ($cells as $cell) {
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $hasFormula = true;
                    }
                }

                $officeCode = mb_strtoupper(
                    trim((string) $cells['office_code']->getValue())
                );

                $code = mb_strtoupper(
                    trim((string) $cells['code']->getValue())
                );

                $name = trim((string) $cells['name']->getValue());

                $status = strtolower(
                    trim((string) $cells['status']->getValue())
                );

                if (
                    $officeCode === ''
                    && $code === ''
                    && $name === ''
                    && $status === ''
                ) {
                    continue;
                }

                $validator = Validator::make([
                    'office_code' => $officeCode,
                    'code' => $code,
                    'name' => $name,
                    'status' => $status,
                ], [
                    'office_code' => ['required', 'string'],
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'status' => [
                        'required',
                        Rule::in(['active', 'inactive']),
                    ],
                ]);

                $errors = $validator->errors()->all();

                if ($hasFormula) {
                    $errors[] = 'Formulas are not allowed in import rows.';
                }

                $officeId = $offices[$officeCode] ?? null;

                if ($officeCode !== '' && $officeId === null) {
                    $errors[] = 'The office code does not exist in the system.';
                }

                if ($officeId !== null && $code !== '') {
                    if (isset($seenCodes[$officeId][$code])) {
                        $errors[] = 'The subject code is duplicated for this office in this file.';
                    }

                    if (isset($existingCodes[$officeId][$code])) {
                        $errors[] = 'The subject code already exists for this office.';
                    }

                    $seenCodes[$officeId][$code] = true;
                }

                $rows[] = [
                    'row' => $rowNumber,
                    'office_code' => $officeCode,
                    'code' => $code,
                    'name' => $name,
                    'status' => $status,
                    'errors' => $errors,
                ];
            }

            if (count($rows) === 0) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook does not contain any subject data.',
                ]);
            }

            $invalidCount = count(array_filter(
                $rows,
                fn ($row) => count($row['errors']) > 0
            ));

            return Inertia::render('subjects/import', [
                'result' => [
                    'rows' => $rows,
                    'total' => count($rows),
                    'invalid' => $invalidCount,
                ],
            ]);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }
}