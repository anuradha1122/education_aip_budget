# Fund import template

## Purpose

Provide a blank XLSX workbook for entering fund data.

## Access

The template download requires authentication and the
`manage-master-data` permission.

## Columns

1. Fund code
2. Fund name
3. Status

The workbook contains headers only. Upload, validation and saving are
not implemented in this step.

## Testing

    php artisan test --filter=FundImportTemplateTest
    php artisan test

## Remaining work

- Add a template download link to the Funds page.
- Implement workbook validation and review.
- Implement authorised saving with audit history.
