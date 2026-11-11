# Designation import template and validation

Users with master data management permission can download a blank Excel
template and validate a completed workbook from the Designations page.

The template contains:

- Designation code
- Designation name
- Status

The workbook must be XLSX, no larger than 2 MB, and contain no more than
500 data rows. Validation checks required fields, status, duplicate codes,
and codes that already exist in the system.

Validation displays row errors without saving any designations. Importing
reviewed designations will be added separately.

## Testing

    php artisan test --filter=DesignationImportTemplateTest
    php artisan test --filter=DesignationImportValidationTest
    npx tsc --noEmit
    npm run build

In the browser, validate workbooks containing valid and invalid rows.