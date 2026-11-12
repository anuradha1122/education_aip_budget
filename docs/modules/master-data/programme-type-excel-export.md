# Programme type Excel export

Users with master data management permission can download the programme
type list as an Excel workbook from the Programme types page.

The workbook contains programme type code, name, and status. It includes
both active and inactive programme types.

## Testing

    php artisan test --filter=ProgrammeTypeExportTest
    npx tsc --noEmit
    npm run build

Download the workbook and compare its contents with the programme type list.