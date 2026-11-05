# Fund import template

## Purpose

Provide a blank XLSX workbook for entering fund data. Administrators
can download it from the Funds page.

## Access

The template download requires authentication and the
`manage-master-data` permission.

## Columns

1. Fund code
2. Fund name
3. Status

The workbook contains headers only. Upload, validation and saving are
not implemented yet.

## Testing

    php artisan test --filter=FundImportTemplateTest
    npx tsc --noEmit
    npm run build

## Remaining work

- Implement workbook validation and review.
- Implement authorised saving with audit history.
