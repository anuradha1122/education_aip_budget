# Subject PDF export

Administrators can download the subject list as a PDF from the Subjects
page. The report includes office code and name, subject code and name,
and status.

Only users with master data management permission can download the PDF.

## Testing

    php artisan test --filter=SubjectPdfExportTest
    npx tsc --noEmit
    npm run build

Download the PDF and compare its contents with the subject list.