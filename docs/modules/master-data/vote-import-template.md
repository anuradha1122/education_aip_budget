# Vote Excel import

Administrators can download a template, validate a completed workbook,
review the results and import valid votes.

## Template columns

- Vote code
- Vote description
- Status: Active or Inactive

## Process

1. Download and complete the Excel template.
2. Upload and validate the workbook.
3. Review the results.
4. If every row is valid, select Import votes.

Validation alone does not save any votes.

## Safeguards

- Access requires authentication and manage-master-data permission.
- The workbook submitted for import must match the reviewed workbook.
- The server validates the workbook again before saving.
- Votes are saved in a transaction; a failure rolls back the import.
- Each imported vote receives a creation audit event with the acting user.
- Workbooks must be XLSX, no larger than 2 MB, with up to 500 data rows.
- Validation checks required fields, status, duplicate codes and formulas.

## Tests

    php artisan test --filter=VoteImportValidationTest
    php artisan test --filter=VoteImportSaveTest
    php artisan test
    npx tsc --noEmit
    npm run build