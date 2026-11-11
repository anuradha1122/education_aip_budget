# Subject import template and validation

Administrators can download a blank Excel template from the Subjects page.

The template contains these columns:

- Office code
- Subject code
- Subject name
- Status

Administrators can upload the completed workbook to review validation
results. The workbook must be XLSX, no larger than 2 MB, and contain no
more than 500 data rows.

Validation checks that the office exists, required details are provided,
the status is Active or Inactive, and the subject code is not duplicated
within the same office. The same subject code can be used by another office.

Validation does not save subjects. Importing reviewed subjects will be
added separately.

Only users with master data management permission can access this feature.

## Testing

    php artisan test --filter=SubjectImportTemplateTest
    php artisan test --filter=SubjectImportValidationTest
    npx tsc --noEmit
    npm run build

In the browser, validate workbooks containing valid rows, an unknown office,
an existing subject code, and invalid values.