<?php

namespace App\Http\Controllers;

use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class VoteImportController extends Controller
{
    private const EXPECTED_HEADERS = [
        'Vote code',
        'Vote description',
        'Status',
    ];

    private const MAX_DATA_ROWS = 500;

    public function index(): Response
    {
        return Inertia::render('votes/import', [
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

            if ($totalColumns > 3) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook must contain only the three template columns.',
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

            foreach (['A1', 'B1', 'C1'] as $address) {
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
                    'file' => 'The headings do not match the vote import template.',
                ]);
            }

            $existingCodes = Vote::query()
                ->pluck('code')
                ->map(fn ($code) => mb_strtoupper(trim($code)))
                ->all();

            $existingCodes = array_fill_keys($existingCodes, true);
            $seenCodes = [];
            $rows = [];

            for ($rowNumber = 2; $rowNumber <= $totalRows; $rowNumber++) {
                $cells = [
                    'code' => $sheet->getCell('A'.$rowNumber),
                    'description' => $sheet->getCell('B'.$rowNumber),
                    'status' => $sheet->getCell('C'.$rowNumber),
                ];

                $hasFormula = false;

                foreach ($cells as $cell) {
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $hasFormula = true;
                    }
                }

                $code = mb_strtoupper(trim((string) $cells['code']->getValue()));
                $description = trim((string) $cells['description']->getValue());
                $status = strtolower(trim((string) $cells['status']->getValue()));

                if ($code === '' && $description === '' && $status === '') {
                    continue;
                }

                $validator = Validator::make([
                    'code' => $code,
                    'description' => $description,
                    'status' => $status,
                ], [
                    'code' => ['required', 'string', 'max:50'],
                    'description' => ['required', 'string', 'max:255'],
                    'status' => ['required', Rule::in(['active', 'inactive'])],
                ]);

                $errors = $validator->errors()->all();

                if ($hasFormula) {
                    $errors[] = 'Formulas are not allowed in import rows.';
                }

                if ($code !== '' && isset($seenCodes[$code])) {
                    $errors[] = 'The vote code is duplicated in this file.';
                }

                if ($code !== '' && isset($existingCodes[$code])) {
                    $errors[] = 'The vote code already exists in the system.';
                }

                if ($code !== '') {
                    $seenCodes[$code] = true;
                }

                $rows[] = [
                    'row' => $rowNumber,
                    'code' => $code,
                    'description' => $description,
                    'status' => $status,
                    'errors' => $errors,
                ];
            }

            if (count($rows) === 0) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook does not contain any vote data.',
                ]);
            }

            $invalidCount = count(array_filter(
                $rows,
                fn ($row) => count($row['errors']) > 0
            ));

            return Inertia::render('votes/import', [
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