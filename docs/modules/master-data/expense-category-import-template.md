# Expense category import template

Users with master data management permission can download a blank Excel
template from the Expense categories page.

The template contains:

- Expense category code
- Expense category name
- Status

Downloading the template does not validate or save categories. Validation
and importing will be added separately.

## Testing

    php artisan test --filter=ExpenseCategoryImportTemplateTest
    npx tsc --noEmit
    npm run build

Download the workbook and check the headings and blank data row.