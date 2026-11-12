# Programme type PDF export

Users with master data management permission can download the programme
type list as a PDF from the Programme types page.

The report contains programme type code, name, and status. It includes
both active and inactive programme types.

## Testing

    php artisan test --filter=ProgrammeTypePdfExportTest
    npx tsc --noEmit
    npm run build

Download the PDF and compare its contents with the programme type list.