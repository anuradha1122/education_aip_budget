# Office Excel import

## Current implementation

An administrator can download a blank Excel import template.

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

- Uploading and validating an Excel file.
- Reporting row-level errors.
- Detecting duplicate codes within the file and in the database.
- Saving valid offices in a transaction.
- Recording an audit event for each imported office.
