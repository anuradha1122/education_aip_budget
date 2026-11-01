# Office Excel export

## Purpose

Allow an authorised administrator to download the office list as an
Excel workbook.

## Access

The export route requires authentication and the manage-master-data
permission.

## Output

The workbook contains:

- Office code
- Office name
- Office type
- Status

The export includes active and inactive offices. It does not currently
apply the search filter on the office page.

Office values are written as text.

## Tests

    php artisan test --filter=OfficeExportTest
    php artisan test
    npm run build

The feature tests check access and read the generated workbook.

## Remaining work

- Add the approved office Excel import workflow.
- Add the PDF office listing.
- Review filtering requirements for exports.
