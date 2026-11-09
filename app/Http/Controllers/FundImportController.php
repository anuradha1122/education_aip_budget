<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use App\Services\FundService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class FundImportController extends Controller
{
    private const EXPECTED_HEADERS = [
        'Fund code',
        'Fund name',
        'Status',
    ];

    private const MAX_DATA_ROWS = 500;

    public function index(): Response
    {
        return Inertia::render('funds/import', [
            'result' => null,
        ]);
    }

    public function validateFile(Request $request): Response
    {
        // A new validation replaces the previous reviewed workbook.
        $request->session()->forget('fund_import_review');

        $result = $this->readWorkbook($request);

        if ($result['invalid'] === 0) {
            $request->session()->put('fund_import_review', [
                'user_id' => $request->user()->id,
                'hash' => hash_file(
                    'sha256',
                    $request->file('file')->getRealPath()
                ),
            ]);
        }

        return Inertia::render('funds/import', [
            'result' => $result,
        ]);
    }

    public function store(
        Request $request,
        FundService $fundService
    ): RedirectResponse {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:2048',
            ],
        ]);

        $review = $request->session()->get('fund_import_review');

        $fileHash = hash_file(
            'sha256',
            $request->file('file')->getRealPath()
        );

        if (
            ! is_array($review)
            || ($review['user_id'] ?? null) !== $request->user()->id
            || ! isset($review['hash'])
            || ! hash_equals($review['hash'], $fileHash)
        ) {
            throw ValidationException::withMessages([
                'file' => 'The selected file has changed or has not been reviewed. Validate it again before importing.',
            ]);
        }

        // Check again because fund data may have changed since validation.
        $result = $this->readWorkbook($request);

        if ($result['invalid'] > 0) {
            $request->session()->forget('fund_import_review');

            throw ValidationException::withMessages([
                'file' => 'The workbook is no longer valid. Validate it again to see the errors.',
            ]);
        }

        try {
            DB::transaction(function () use ($result, $fundService, $request) {
                foreach ($result['rows'] as $row) {
                    $fundService->create([
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'is_active' => $row['status'] === 'active',
                    ], $request->user());
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            $request->session()->forget('fund_import_review');

            throw ValidationException::withMessages([
                'file' => 'A fund code already exists. Validate the workbook again.',
            ]);
        } catch (ValidationException $exception) {
            $request->session()->forget('fund_import_review');

            throw ValidationException::withMessages([
                'file' => 'A fund could not be imported. Validate the workbook again.',
            ]);
        }

        $request->session()->forget('fund_import_review');

        return redirect()->route('funds.index');
    }

    private function readWorkbook(Request $request): array
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:2048',
            ],
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
                    'file' => 'The headings do not match the fund import template.',
                ]);
            }

            $rows = [];
            $seenCodes = [];

            $existingCodes = Fund::query()
                ->pluck('code')
                ->map(fn ($code) => mb_strtoupper(trim($code)))
                ->all();

            $existingCodes = array_fill_keys($existingCodes, true);

            for ($rowNumber = 2; $rowNumber <= $totalRows; $rowNumber++) {
                $cells = [
                    'code' => $sheet->getCell('A'.$rowNumber),
                    'name' => $sheet->getCell('B'.$rowNumber),
                    'status' => $sheet->getCell('C'.$rowNumber),
                ];

                $hasFormula = false;

                foreach ($cells as $cell) {
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $hasFormula = true;
                    }
                }

                $code = mb_strtoupper(trim((string) $cells['code']->getValue()));
                $name = trim((string) $cells['name']->getValue());
                $status = strtolower(trim((string) $cells['status']->getValue()));

                if ($code === '' && $name === '' && $status === '') {
                    continue;
                }

                $validator = Validator::make([
                    'code' => $code,
                    'name' => $name,
                    'status' => $status,
                ], [
                    'code' => [
                        'required',
                        'string',
                        'max:50',
                    ],
                    'name' => [
                        'required',
                        'string',
                        'max:255',
                    ],
                    'status' => [
                        'required',
                        Rule::in(['active', 'inactive']),
                    ],
                ]);

                $errors = $validator->errors()->all();

                if ($hasFormula) {
                    $errors[] = 'Formulas are not allowed in import rows.';
                }

                if ($code !== '' && isset($seenCodes[$code])) {
                    $errors[] = 'The fund code is duplicated in this file.';
                }

                if ($code !== '' && isset($existingCodes[$code])) {
                    $errors[] = 'The fund code already exists in the system.';
                }

                if ($code !== '') {
                    $seenCodes[$code] = true;
                }

                $rows[] = [
                    'row' => $rowNumber,
                    'code' => $code,
                    'name' => $name,
                    'status' => $status,
                    'errors' => $errors,
                ];
            }

            if (count($rows) === 0) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook does not contain any fund data.',
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