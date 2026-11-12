# Programme type import template

Users with master data management permission can download a blank Excel
template from the Programme types page.

The template contains:

- Programme type code
- Programme type name
- Status

Downloading the template does not validate or save programme types.
Validation and importing will be added separately.

## Testing

    php artisan test --filter=ProgrammeTypeImportTemplateTest
    npx tsc --noEmit
    npm run build

Download the workbook and check the headings and blank data row.