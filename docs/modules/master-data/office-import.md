# Office Excel import

## Current implementation

An administrator can download the template, validate a workbook and
import valid office rows.

The import request reads and validates the submitted workbook again.
If any row has an error, no offices from that file are saved.

The entire batch is saved in one database transaction. Each imported
office is created through OfficeService and receives an audit event.

Existing offices are not updated by the import.

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

