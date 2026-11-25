<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAccessReviewExportController extends Controller
{
    public function excel(Request $request): StreamedResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['search'] ?? '');

        return response()->streamDownload(function () use ($search) {
            $book = new Spreadsheet();
            try {
                $sheet = $book->getActiveSheet();
                $sheet->setTitle('Access review');
                $rows = DB::table('users as u')
                    ->leftJoin('user_access_assignments as a', 'a.user_id', '=', 'u.id')
                    ->leftJoin('offices as o', 'o.id', '=', 'a.office_id')
                    ->leftJoin('user_officer_links as l', 'l.user_id', '=', 'u.id')
                    ->leftJoin('officers as officer', 'officer.id', '=', 'l.officer_id')
                    ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('u.name', 'like', '%'.$search.'%')->orWhere('u.email', 'like', '%'.$search.'%')->orWhere('o.name', 'like', '%'.$search.'%')->orWhere('o.code', 'like', '%'.$search.'%')))
                    ->orderBy('u.name')->orderBy('u.id')
                    ->select('u.id', 'u.name', 'u.email', 'u.is_active', 'u.is_admin', 'u.can_import_aip', 'a.role', 'o.code as office_code', 'o.name as office_name', 'o.is_active as office_active', 'officer.name as officer_name', 'officer.is_active as officer_active')
                    ->get();
                $accounts = User::query()->whereIn('id', $rows->pluck('id'))->get(['id', 'is_admin', 'is_active', 'can_import_aip'])->keyBy('id');
                $headers = ['Name', 'Email', 'Status', 'Recorded role', 'Office code', 'Office', 'Office status', 'Linked officer', 'Officer status', 'Administrator', 'Import flag', 'AIP register', 'AIP import', 'Financial years', 'Organisational data', 'AIP assignments'];
                foreach ($headers as $i => $value) $sheet->setCellValueExplicit(chr(65 + $i).'1', $value, DataType::TYPE_STRING);
                foreach ($rows as $index => $row) {
                    $active = (bool) $row->is_active;
                    $user = $accounts->get($row->id);
                    $values = [$row->name, $row->email, $active ? 'Active' : 'Inactive', $row->role ?? '', $row->office_code ?? '', $row->office_name ?? '', $row->office_name === null ? '' : ($row->office_active ? 'Active' : 'Inactive'), $row->officer_name ?? '', $row->officer_name === null ? '' : ($row->officer_active ? 'Active' : 'Inactive'), $row->is_admin ? 'Yes' : 'No', $row->can_import_aip ? 'Yes' : 'No', $active && Gate::forUser($user)->allows('view-aip-register') ? 'Allowed' : 'Not allowed', $active && Gate::forUser($user)->allows('import-aip') ? 'Allowed' : 'Not allowed', $active && Gate::forUser($user)->allows('manage-financial-years') ? 'Allowed' : 'Not allowed', $active && Gate::forUser($user)->allows('manage-organisational-data') ? 'Allowed' : 'Not allowed', $active && Gate::forUser($user)->allows('manage-aip-assignments') ? 'Allowed' : 'Not allowed'];
                    foreach ($values as $i => $value) $sheet->setCellValueExplicit(chr(65 + $i).($index + 2), (string) $value, DataType::TYPE_STRING);
                }
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'user-access-review.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }
}
