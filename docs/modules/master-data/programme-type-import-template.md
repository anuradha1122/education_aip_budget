# Programme type import

Users with master data management permission can download the Excel
template, validate a completed workbook, and import reviewed programme
types.

The template contains:

- Programme type code
- Programme type name
- Status

The workbook must be XLSX, no larger than 2 MB, and contain no more than
500 data rows. Validation checks required fields, status, duplicate codes,
and codes that already exist in the system.

The Import programme types button appears after a valid workbook is
reviewed. The server checks the file and validates its contents again
before saving. Imported programme types are recorded in the audit history.

## Testing

    php artisan test --filter=ProgrammeTypeImportTemplateTest
    php artisan test --filter=ProgrammeTypeImportValidationTest
    php artisan test --filter=ProgrammeTypeImportSaveTest
    npx tsc --noEmit
    npm run build

Also validate and import a workbook in the browser, then check the
programme type list and audit history.