# Fund list PDF export

## Purpose

Download the fund list as an A4 PDF.

## Access

The export requires authentication and the `manage-master-data`
permission.

## Contents

The PDF contains fund code, name and active or inactive status.
Both active and inactive funds are included.

## Testing

    php artisan test --filter=FundPdfExportTest
    php artisan test

## Remaining work

Add a PDF download link to the Funds page.
