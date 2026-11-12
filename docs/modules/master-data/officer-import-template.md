# Officer import template

Users with master data management permission can download a blank Excel
template from the Officers page.

The template contains:

- Officer name
- Office code
- Designation code
- Status

Downloading the template does not validate or save officers. Validation
and importing will be added separately.

Officer names are not unique in the current system. A duplicate handling
rule needs to be considered when the import workflow is designed.

## Testing

    php artisan test --filter=OfficerImportTemplateTest
    npx tsc --noEmit
    npm run build

Download the workbook and check the headings and blank data row.