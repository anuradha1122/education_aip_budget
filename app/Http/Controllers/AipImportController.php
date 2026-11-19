<?php

namespace App\Http\Controllers;

use App\Services\AipActivityService;
use App\Services\AipActivityOfficeService;
use App\Services\AipActivityFundingEntryService;
use Illuminate\Http\RedirectResponse;
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
        $request->session()->forget('aip_import_review');
        $result = $this->reviewFile($request);
        $request->session()->put('aip_import_review', [
            'year' => (string) $request->input('financial_year_id'),
            'office' => (string) $request->input('office_id'),
            'hash' => hash_file('sha256', $request->file('file')->getRealPath()),
        ]);
        return Inertia::render('aip-activities/import', [
            'years' => $this->openYears(), 'offices' => $this->offices(), 'result' => $result,
        ]);
    }

    private function reviewFile(Request $request): array
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
        if (! (bool) $office->is_active) {
            throw ValidationException::withMessages(['office_id' => 'The selected office is inactive.']);
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
            // Number formats are needed to preserve identifiers displayed with leading zeros.
            $reader->setReadDataOnly(false);
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
                $activityNumberError = null;

                foreach (range('A', 'J') as $column) {
                    $cell = $sheet->getCell($column.$rowNumber);
                    $value = $cell->getValue();
                    if ($column === 'C' && $cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC) {
                        $format = $sheet->getStyle($column.$rowNumber)->getNumberFormat()->getFormatCode();
                        $numeric = (float) $value;
                        if (! is_finite($numeric) || $numeric < 0 || floor($numeric) !== $numeric || $numeric > 999999999999999
                            || (strcasecmp($format, 'General') !== 0 && (! preg_match('/\A0+\z/', $format) || strlen($format) > 50))) {
                            $activityNumberError = 'Activity number has an unsupported numeric value or format. Enter it as text.';
                        } else {
                            $digits = sprintf('%.0f', $numeric);
                            $value = strcasecmp($format, 'General') === 0 ? $digits : str_pad($digits, strlen($format), '0', STR_PAD_LEFT);
                        }
                    }
                    $cells[] = trim((string) $value);
                }

                if (count(array_filter($cells, fn ($value) => $value !== '')) === 0) {
                    continue;
                }

                $number = $cells[2];
                $errors = [];

                if ($activityNumberError !== null) {
                    $errors[] = $activityNumberError;
                } elseif ($number === '' || ! preg_match('/\A[0-9]+\z/', $number)) {
                    $errors[] = 'Activity number must contain digits only.';
                } elseif (strlen($number) > 50) {
                    $errors[] = 'Activity number must not exceed 50 characters.';
                } else {
                    $numberRows[$number][] = count($rows);
                }

                if ($cells[3] === '') {
                    $errors[] = 'Activity description is required.';
                }

                if (mb_strlen($cells[1]) > 255) {
                    $errors[] = 'Programme level must not exceed 255 characters.';
                }

                $voteCode = $cells[8];
                $fundCode = $cells[9];
                if ($voteCode !== '') {
                    $vote = DB::table('votes')->where('code', $voteCode)->first(['id', 'is_active']);
                    if ($vote === null) {
                        $errors[] = 'Vote code was not found in master data.';
                    } elseif (! (bool) $vote->is_active) {
                        $errors[] = 'Vote is inactive.';
                    }
                }
                if ($fundCode !== '') {
                    $fund = DB::table('funds')->where('code', $fundCode)->first(['id', 'is_active']);
                    if ($fund === null) {
                        $errors[] = 'Fund code was not found in master data.';
                    } elseif (! (bool) $fund->is_active) {
                        $errors[] = 'Fund is inactive.';
                    }
                }

                $subjectMatches = collect();
                if ($cells[4] !== '') {
                    $subjectMatches = DB::table('subjects')
                        ->where('office_id', $office->id)
                        ->where('code', $cells[4])
                        ->limit(2)
                        ->get(['id', 'name', 'is_active']);
                    if ($subjectMatches->isEmpty()) {
                        $errors[] = 'Subject code was not found for the selected office.';
                    } elseif ($subjectMatches->count() > 1) {
                        $errors[] = 'Subject code matches more than one record in the selected office.';
                    } elseif ($cells[5] !== '' && trim((string) $subjectMatches[0]->name) !== $cells[5]) {
                        $errors[] = 'Subject name differs from master data.';
                    }
                    if ($subjectMatches->count() === 1 && ! (bool) $subjectMatches[0]->is_active) {
                        $errors[] = 'Subject is inactive.';
                    }
                }

                $officerName = $cells[6];
                $designationName = $cells[7];
                $matches = collect();
                if ($officerName !== '') {
                    $matches = DB::table('officers')
                        ->where('office_id', $office->id)
                        ->where('name', $officerName)
                        ->limit(2)
                        ->get(['id', 'designation_id', 'is_active']);
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
                    if ($matches->count() === 1 && ! (bool) $matches[0]->is_active) {
                        $errors[] = 'Officer is inactive.';
                    }
                } elseif ($designationName !== '') {
                    $errors[] = 'Designation cannot be checked without an officer name.';
                }

                if ($subjectMatches->count() === 1 && $matches->count() === 1
                    && ! DB::table('officer_subject_assignments')
                        ->where('subject_id', $subjectMatches[0]->id)
                        ->where('officer_id', $matches[0]->id)
                        ->whereNull('ended_at')
                        ->exists()) {
                    $errors[] = 'Officer does not have an active assignment to this subject.';
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
                    ->get(['activity.id', 'activity.activity_number', 'activity.description', 'activity.level', 'link.id as office_link_id']);

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

                    if (($activity->level ?? '') !== '' && $activity->level !== $row['level']) {
                        $row['errors'][] = 'Programme level differs from the existing activity.';
                    }

                    if ($activity->office_link_id !== null) {
                        $row['errors'][] = 'This activity is already linked to the selected office.';
                    }

                    if ($row['fund'] !== '') {
                        $fundId = DB::table('funds')->where('code', $row['fund'])->value('id');
                        if ($fundId !== null && DB::table('aip_activity_funding_entries')
                            ->where('aip_activity_id', $activity->id)
                            ->where('fund_id', '<>', $fundId)
                            ->exists()) {
                            $row['errors'][] = 'Fund differs from the existing fund for this activity.';
                        }
                    }

                    if ($row['vote'] !== '') {
                        $voteId = DB::table('votes')->where('code', $row['vote'])->value('id');
                        if ($voteId !== null && DB::table('aip_activity_funding_entries')
                            ->where('aip_activity_id', $activity->id)
                            ->where('vote_id', '<>', $voteId)
                            ->exists()) {
                            $row['errors'][] = 'Vote differs from the existing vote for this activity.';
                        }
                    }
                }
                foreach ([
                    'subject_code' => 'Subject code is required.',
                    'officer' => 'Officer is required.',
                    'vote' => 'Vote is required.',
                    'fund' => 'Fund is required.',
                ] as $field => $message) {
                    if ($row[$field] === '') {
                        $row['errors'][] = $message;
                    }
                }
            }
            unset($row);

            return [
                'financial_year' => $year->year,
                'office' => ['code' => $office->code, 'name' => $office->name],
                'rows' => $rows,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function save(Request $request): RedirectResponse
    {
        $request->validate([
            'financial_year_id' => ['required', 'integer'],
            'office_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);
        $review = $request->session()->get('aip_import_review');
        $hash = hash_file('sha256', $request->file('file')->getRealPath());
        if (! is_array($review)
            || ($review['year'] ?? null) !== (string) $request->input('financial_year_id')
            || ($review['office'] ?? null) !== (string) $request->input('office_id')
            || ! is_string($review['hash'] ?? null)
            || ! hash_equals($review['hash'], $hash)) {
            throw ValidationException::withMessages(['file' => 'Review this workbook for the selected year and office before saving.']);
        }

        try {
            DB::transaction(function () use ($request) {
            // Serialize with year closing and the existing creation services.
            $year = DB::table('financial_years')->where('id', $request->input('financial_year_id'))
                ->lockForUpdate()->first();
            if ($year === null || (bool) $year->is_closed) {
                throw ValidationException::withMessages(['financial_year_id' => 'Select an existing open financial year.']);
            }
            $office = DB::table('offices')->where('id', $request->input('office_id'))->lockForUpdate()->first();
            if ($office === null) {
                throw ValidationException::withMessages(['office_id' => 'Select an existing office.']);
            }
            if (! (bool) $office->is_active) {
                throw ValidationException::withMessages(['office_id' => 'The selected office is inactive.']);
            }
            $result = $this->reviewFile($request);
            $notice = 'Activity already exists for this financial year; review its existing details before importing.';
            foreach ($result['rows'] as $row) {
                $blocking = array_filter($row['errors'], fn ($error) => $error !== $notice);
                if (count($blocking) > 0) {
                    throw ValidationException::withMessages(['file' => 'Workbook validation changed or has errors. Review it again before saving.']);
                }
            }
            foreach ($result['rows'] as $row) {
                $officers = DB::table('officers')->where('office_id', $office->id)
                    ->where('name', $row['officer'])->limit(2)->lockForUpdate()->get();
                $subjects = DB::table('subjects')->where('office_id', $office->id)
                    ->where('code', $row['subject_code'])->limit(2)->lockForUpdate()->get();
                if ($officers->count() !== 1 || $subjects->count() !== 1) {
                    throw ValidationException::withMessages(['file' => 'Master data changed or is ambiguous. Review the workbook again.']);
                }
                $officer = $officers[0];
                $subject = $subjects[0];
                $vote = DB::table('votes')->where('code', $row['vote'])->lockForUpdate()->first(['id', 'is_active']);
                $fund = DB::table('funds')->where('code', $row['fund'])->lockForUpdate()->first(['id', 'is_active']);
                if ($vote === null || $fund === null || ! (bool) $subject->is_active || ! (bool) $officer->is_active || ! (bool) $vote->is_active || ! (bool) $fund->is_active) {
                    throw ValidationException::withMessages(['file' => 'Master data changed. Review the workbook again.']);
                }
                $voteId = $vote->id;
                $fundId = $fund->id;
                if ($row['designation'] !== '' && ! DB::table('designations')->where('id', $officer->designation_id)->where('name', $row['designation'])->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['file' => 'A designation changed. Review the workbook again.']);
                }
                if ($row['subject'] !== '' && trim((string) $subject->name) !== $row['subject']) {
                    throw ValidationException::withMessages(['file' => 'A subject name differs from master data. Review the workbook.']);
                }
                $activity = DB::table('aip_activities')->where('financial_year_id', $year->id)
                    ->where('activity_number', $row['activity_number'])->lockForUpdate()->first();
                if ($activity !== null && trim((string) $activity->description) !== $row['activity']) {
                    throw ValidationException::withMessages(['file' => 'Row '.$row['row_number'].': activity description changed. Review the workbook again.']);
                }
                if ($activity !== null && (string) ($activity->level ?? '') !== $row['level']) {
                    throw ValidationException::withMessages(['file' => 'Row '.$row['row_number'].': programme level differs from the existing activity. Correct it before importing.']);
                }
                if ($activity === null) {
                    $activity = app(AipActivityService::class)->create([
                        'financial_year_id' => $year->id, 'activity_number' => $row['activity_number'],
                        'description' => $row['activity'], 'level' => $row['level'],
                    ], $request->user());
                }
                app(AipActivityOfficeService::class)->create([
                    'aip_activity_id' => $activity->id, 'office_id' => $request->input('office_id'),
                    'subject_id' => $subject->id, 'officer_id' => $officer->id,
                ], $request->user());
                $funding = DB::table('aip_activity_funding_entries')
                    ->where('aip_activity_id', $activity->id)->lockForUpdate()->first();
                if ($funding !== null && ((string) $funding->fund_id !== (string) $fundId || (string) $funding->vote_id !== (string) $voteId)) {
                    throw ValidationException::withMessages(['file' => 'Row '.$row['row_number'].': fund or vote changed. Review the workbook again.']);
                }
                if ($funding === null) {
                    app(AipActivityFundingEntryService::class)->create([
                        'aip_activity_id' => $activity->id, 'fund_id' => $fundId, 'vote_id' => $voteId,
                    ], $request->user());
                }
            }
            });
        } catch (ValidationException $exception) {
            $messages = $exception->errors();
            if (isset($messages['file']) || isset($messages['financial_year_id']) || isset($messages['office_id'])) {
                throw $exception;
            }
            throw ValidationException::withMessages([
                'file' => collect($messages)->flatten()->first() ?? 'The workbook could not be saved. Review it again.',
            ]);
        }
        $request->session()->forget('aip_import_review');
        return redirect()->route('aip-activities.index')->with('success', 'AIP workbook imported successfully.');
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
