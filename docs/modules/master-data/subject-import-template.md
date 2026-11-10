# Subject import template

Administrators can download a blank Excel template from the Subjects page.

The template contains these columns:

- Office code
- Subject code
- Subject name
- Status

Only users with master data management permission can download it.

The template is for a future import workflow. Downloading it does not
validate or save subjects.

## Testing

    php artisan test --filter=SubjectImportTemplateTest
    npx tsc --noEmit
    npm run build

Open the downloaded workbook and check the headings and blank data row.