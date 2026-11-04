# Fund list Excel export

## Purpose

Download the fund list as an XLSX workbook from the Funds page.

## Access

The export requires authentication and the `manage-master-data`
permission.

## Contents

The workbook includes fund code, fund name and active or inactive
status. It includes both active and inactive funds.

Fund values are written as text so they are not interpreted as
spreadsheet formulas.

## Testing

    php artisan test --filter=FundExportTest
    npx tsc --noEmit
    npm run build
