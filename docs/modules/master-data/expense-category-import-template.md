# Expense category import template and validation

Users with master data management permission can download a blank Excel
template and validate a completed workbook from the Expense categories page.

The template contains:

- Expense category code
- Expense category name
- Status

The workbook must be XLSX, no larger than 2 MB, and contain no more than
500 data rows. Validation checks required fields, status, duplicate codes,
and codes that already exist in the system.

Validation displays row errors without saving categories. Importing
reviewed categories will be added separately.

## Testing

    php artisan test --filter=ExpenseCategoryImportTemplateTest
    php artisan test --filter=ExpenseCategoryImportValidationTest
    npx tsc --noEmit
    npm run build

In the browser, validate workbooks containing valid and invalid rows.