# Officer PDF export

Users with master data management permission can download the officer list
as a PDF from the Officers page.

The report includes each officer's name, current office code and name,
current designation code and name, and status. It includes both active
and inactive officers.

## Testing

    php artisan test --filter=OfficerPdfExportTest
    npx tsc --noEmit
    npm run build

Download the PDF and compare its contents with the officer list.