# Officer import template and validation

Users with master data management permission can download a blank Excel
template and validate a completed workbook from the Officers page.

The template contains:

- Officer name
- Office code
- Designation code
- Status

The workbook must be XLSX, no larger than 2 MB, and contain no more than
500 data rows. Validation checks required fields, office and designation
codes, and status.

Rows with the same name, office, and designation as an existing officer or
an earlier row receive a possible-duplicate warning. A warning does not
establish that the records belong to the same person.

Validation does not save officers. Reviewed import will be added separately.

## Testing

    php artisan test --filter=OfficerImportTemplateTest
    php artisan test --filter=OfficerImportValidationTest
    npx tsc --noEmit
    npm run build

In the browser, validate workbooks containing valid rows, invalid values,
and possible duplicates.