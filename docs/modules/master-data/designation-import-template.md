# Designation import

Users with master data management permission can download the Excel
template, validate a completed workbook, and import reviewed designations.

The template contains:

- Designation code
- Designation name
- Status

The workbook must be XLSX, no larger than 2 MB, and contain no more than
500 data rows. Validation checks required fields, status, duplicate codes,
and codes that already exist in the system.

The Import designations button appears after a valid workbook is reviewed.
The server checks the file and validates its contents again before saving.
Imported designations are recorded in the designation audit history.

## Testing

    php artisan test --filter=DesignationImportTemplateTest
    php artisan test --filter=DesignationImportValidationTest
    php artisan test --filter=DesignationImportSaveTest
    npx tsc --noEmit
    npm run build

Also validate and import a workbook in the browser, then check the
designation list and audit history.