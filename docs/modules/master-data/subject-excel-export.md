# Subject Excel export

Administrators can download the subject list as an Excel workbook from
the Subjects page.

The workbook includes office and subject details and their status.
Only users with master data management permission can download it.

## Testing

Run:

    php artisan test --filter=SubjectExportTest
    npx tsc --noEmit
    npm run build

Also download the workbook from the Subjects page and compare its contents
with the records shown in the application.