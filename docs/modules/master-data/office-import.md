# Office Excel import

## Current implementation

An administrator can download the template and upload a completed XLSX
file for validation.

Validation reports row-level errors for required fields, office type,
status and duplicate codes. This stage does not save office records.

The workbook must have one worksheet, the four template headings, no
more than 500 data rows and a maximum file size of 2 MB. Formulas are
not accepted in import data.

## Template columns

1. Office code
2. Office name
3. Office type
4. Status

## Access

The template route requires authentication and the manage-master-data
permission.

## Tests

    php artisan test --filter=OfficeImportTemplateTest
    php artisan test
    npm run build

## Not yet implemented

- Saving valid offices in a transaction.
- Recording an audit event for each imported office.
