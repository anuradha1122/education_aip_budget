# Vote Excel import

Administrators can download a blank Excel template and upload a
completed workbook for validation.

## Template columns

- Vote code
- Vote description
- Status: Active or Inactive

## Validation

The workbook must be an XLSX file with one worksheet and the three
template headings. It can be no larger than 2 MB and can contain up to
500 data rows.

Validation checks required fields, status, duplicate vote codes, existing
vote codes and formulas. Errors are displayed by row.

Validation does not save any votes. The download and validation routes
require authentication and the manage-master-data permission.

## Tests

    php artisan test --filter=VoteImportTemplateTest
    php artisan test --filter=VoteImportValidationTest
    php artisan test
    npx tsc --noEmit
    npm run build

## Remaining work

- Import reviewed, valid votes and record audit events.