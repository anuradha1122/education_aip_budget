# Fund import

Administrators can import funds from the Excel template.

## Process

1. Download and complete the fund import template.
2. Upload the workbook and validate it.
3. Review the results.
4. If every row is valid, select Import funds.

Validation does not save any funds.

## Safeguards

- Only users with master-data management permission can import.
- The workbook submitted for import must match the reviewed workbook.
- The server validates the workbook again before saving.
- The import uses a transaction: if saving fails, the import is rolled back.
- Each imported fund receives a creation audit event with the acting user.
- A workbook can contain up to 500 data rows and must be no larger than 2 MB.

## Testing

    php artisan test --filter=FundImportSaveTest
    php artisan test
    npx tsc --noEmit
    npm run build