# Designation Excel export

Users with master data management permission can download the designation
list as an Excel workbook from the Designations page.

The workbook contains designation code, name, and status.

## Testing

    php artisan test --filter=DesignationExportTest
    npx tsc --noEmit
    npm run build

Download the workbook and compare its contents with the designation list.