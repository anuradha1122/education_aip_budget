# Designation import template

Users with master data management permission can download a blank Excel
template from the Designations page.

The template contains:

- Designation code
- Designation name
- Status

Downloading the template does not validate or save designations. Validation
and importing will be added separately.

## Testing

    php artisan test --filter=DesignationImportTemplateTest
    npx tsc --noEmit
    npm run build

Download the workbook and check the headings and blank data row.