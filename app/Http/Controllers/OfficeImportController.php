<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use App\Services\OfficeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class OfficeImportController extends Controller
{
    private const EXPECTED_HEADERS = [
        'Office code',
        'Office name',
        'Office type',
        'Status',
    ];

    private const MAX_DATA_ROWS = 500;

    public function index(): Response
    {
        return Inertia::render('offices/import', [
            'result' => null,
            'importMessage' => session('import_success'),
        ]);
    }

    public function validateFile(Request $request): Response|RedirectResponse
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
                    'file' => 'The headings do not match the office import template.',
                ]);
            }

            $rows = [];
            $seenCodes = [];
            $existingCodes = Office::query()
                ->pluck('code')
                ->map(fn ($code) => strtoupper(trim($code)))
                ->all();

            $existingCodes = array_fill_keys($existingCodes, true);

            for ($rowNumber = 2; $rowNumber <= $totalRows; $rowNumber++) {
                $cells = [
                    'code' => $sheet->getCell('A'.$rowNumber),
                    'name' => $sheet->getCell('B'.$rowNumber),
                    'type' => $sheet->getCell('C'.$rowNumber),
                    'status' => $sheet->getCell('D'.$rowNumber),
                ];

                $hasFormula = false;

                foreach ($cells as $cell) {
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $hasFormula = true;
                    }
                }

                $code = strtoupper(trim((string) $cells['code']->getValue()));
                $name = trim((string) $cells['name']->getValue());
                $type = strtolower(trim((string) $cells['type']->getValue()));
                $status = strtolower(trim((string) $cells['status']->getValue()));

                if (
                    $code === ''
                    && $name === ''
                    && $type === ''
                    && $status === ''
                ) {
                    continue;
                }

                $validator = Validator::make([
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'status' => $status,
                ], [
                    'code' => [
                        'required',
                        'string',
                        'max:30',
                        'regex:/^[A-Z0-9_-]+$/',
                    ],
                    'name' => [
                        'required',
                        'string',
                        'max:150',
                    ],
                    'type' => [
                        'required',
                        Rule::in(['provincial', 'zonal']),
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
                    $errors[] = 'The office code is duplicated in this file.';
                }

                if ($code !== '' && isset($existingCodes[$code])) {
                    $errors[] = 'The office code already exists in the system.';
                }

                if ($code !== '') {
                    $seenCodes[$code] = true;
                }

                $rows[] = [
                    'row' => $rowNumber,
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'status' => $status,
                    'errors' => $errors,
                ];
            }

            if (count($rows) === 0) {
                throw ValidationException::withMessages([
                    'file' => 'The workbook does not contain any office data.',
                ]);
            }

            $invalidCount = count(array_filter(
                $rows,
                fn ($row) => count($row['errors']) > 0
            ));

            if ($request->attributes->get('save_offices') === true) {
                if ($invalidCount > 0) {
                    return Inertia::render('offices/import', [
                        'result' => [
                            'rows' => $rows,
                            'total' => count($rows),
                            'invalid' => $invalidCount,
                        ],
                        'importMessage' => 'No offices were saved. Correct the errors and try again.',
                    ]);
                }

                $officeService = app(OfficeService::class);

                DB::transaction(function () use ($rows, $officeService): void {
                    foreach ($rows as $row) {
                        $officeService->create([
                            'code' => $row['code'],
                            'name' => $row['name'],
                            'type' => $row['type'],
                            'is_active' => $row['status'] === 'active',
                        ]);
                    }
                });

                return redirect()
                    ->route('offices.import.index')
                    ->with('import_success', count($rows).' offices imported successfully.');
            }

            return Inertia::render('offices/import', [
                'result' => [
                    'rows' => $rows,
                    'total' => count($rows),
                    'invalid' => $invalidCount,
                ],
                'importMessage' => null,
            ]);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $request->attributes->set('save_offices', true);

        return $this->validateFile($request);
    }
}
