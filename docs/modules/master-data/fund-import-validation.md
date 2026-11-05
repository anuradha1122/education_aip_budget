# Fund import validation

## Purpose

Check a completed fund import workbook and display row-level results
before saving.

## Access

The validation page and route require authentication and the
`manage-master-data` permission.

## Workbook

- XLSX format.
- Maximum size: 2 MB.
- Exactly one worksheet.
- Maximum 500 data rows.
- Headings: Fund code, Fund name, Status.
- Status: Active or Inactive.
- Formulas are not accepted in headings or data rows.

## Validation

Codes and names are required. Codes are trimmed and converted to
uppercase. Duplicate codes in the workbook and existing funds are
reported.

Validation does not save funds or create audit events.

## Testing

    php artisan test --filter=FundImportValidationTest
    php artisan test
    npx tsc --noEmit
    npm run build

## Remaining work

- Add a link from the Funds page.
- Implement reviewed-file import and audit recording.
