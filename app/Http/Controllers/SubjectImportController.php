<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        $request->session()->forget('subject_import_review');
        $result = $this->readWorkbook($request);

        if ($result['invalid'] === 0) {
            $request->session()->put('subject_import_review', [
                'user_id' => $request->user()->id,
                'hash' => hash_file('sha256', $request->file('file')->getRealPath()),
                'office_ids' => array_column($result['rows'], 'office_id'),
            ]);
        }

        return Inertia::render('subjects/import', ['result' => $result]);
    }

    public function store(Request $request, SubjectService $subjectService): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
        ]);

        $review = $request->session()->pull('subject_import_review');
        $hash = hash_file('sha256', $request->file('file')->getRealPath());

        if (! is_array($review)
            || ($review['user_id'] ?? null) !== $request->user()->id
            || ! is_string($review['hash'] ?? null)
            || ! hash_equals($review['hash'], $hash)) {
            throw ValidationException::withMessages([
                'file' => 'The selected file has changed or has not been reviewed. Validate it again before importing.',
            ]);
        }

        $result = $this->readWorkbook($request);

        if ($result['invalid'] > 0 || ($review['office_ids'] ?? null) !== array_column($result['rows'], 'office_id')) {
            throw ValidationException::withMessages([
                'file' => 'The workbook is no longer valid or an office has changed. Validate it again before importing.',
            ]);
        }

        try {
            DB::transaction(function () use ($result, $subjectService, $request) {
                foreach ($result['rows'] as $row) {
                    $subjectService->create([
                        'office_id' => $row['office_id'],
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'is_active' => $row['status'] === 'active',
                    ], $request->user());
                }
            });
        } catch (UniqueConstraintViolationException | ValidationException $exception) {
            throw ValidationException::withMessages([
                'file' => 'A subject could not be imported. Validate the workbook again.',
            ]);
        }

        return redirect()->route('subjects.index');
    }

    private function readWorkbook(Request $request): array
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
                    'office_id' => $officeId,
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

            return [
                'rows' => $rows,
                'total' => count($rows),
                'invalid' => $invalidCount,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }
}
