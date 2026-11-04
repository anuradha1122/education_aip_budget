# Fund list PDF export

## Purpose

Download the fund list as an A4 PDF from the Funds page.

## Access

The export requires authentication and the `manage-master-data`
permission.

## Contents

The PDF contains fund code, name and active or inactive status.
Both active and inactive funds are included.

## Testing

    php artisan test --filter=FundPdfExportTest
    npx tsc --noEmit
    npm run build
