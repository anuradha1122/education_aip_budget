# Officer import

Users with master data management permission can download the Excel
template, validate a completed workbook, and import reviewed officers.

The template contains:

- Officer name
- Office code
- Designation code
- Status

The workbook must be XLSX, no larger than 2 MB, and contain no more than
500 data rows. Validation checks required fields, office and designation
codes, status, and names containing only numbers.

A row with the same name, office, and designation as an existing officer or
an earlier row receives a possible-duplicate warning. The user must review
and acknowledge warnings before importing. A warning does not establish
that the records belong to the same person.

The server checks the reviewed workbook and validation results again
before saving. Imported officers are recorded in the officer audit history.

## Testing

    php artisan test --filter=OfficerImportTemplateTest
    php artisan test --filter=OfficerImportValidationTest
    php artisan test --filter=OfficerImportSaveTest
    npx tsc --noEmit
    npm run build

Also test valid, invalid, and possible-duplicate workbooks in the browser.
Check the officer list and audit history after importing.