# Designation PDF export

Users with master data management permission can download the designation
list as a PDF from the Designations page.

The report contains designation code, name, and status.

## Testing

    php artisan test --filter=DesignationPdfExportTest
    npx tsc --noEmit
    npm run build

Download the PDF and compare its contents with the designation list.