# Subject import

Administrators can download a blank Excel template from the Subjects page.

The template contains:

- Office code
- Subject code
- Subject name
- Status

The workbook must be XLSX, no larger than 2 MB, and contain no more than
500 data rows.

Administrators can validate the workbook and review each row before
importing. Validation checks required values, office codes, status, and
duplicate subject codes within each office.

The Import subjects button appears after a valid workbook is reviewed.
The server checks the workbook again before saving. It rejects a changed
file, a newly conflicting subject code, or a changed office assignment.
Imported subjects are recorded in the subject audit history.

Only users with master data management permission can use this feature.

## Testing

    php artisan test --filter=SubjectImportValidationTest
    php artisan test --filter=SubjectImportSaveTest
    npx tsc --noEmit
    npm run build

Also validate and import a workbook in the browser, then check the subject
list and audit history.