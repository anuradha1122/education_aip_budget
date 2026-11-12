# Officer Excel export

Users with master data management permission can download the officer list
as an Excel workbook from the Officers page.

The workbook includes each officer's name, current office code and name,
current designation code and name, and active status. It includes both
active and inactive officers.

## Testing

    php artisan test --filter=OfficerExportTest
    npx tsc --noEmit
    npm run build

Download the workbook and compare its contents with the officer list.