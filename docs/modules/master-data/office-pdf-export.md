# Office PDF export

## Purpose

Allow an authorised administrator to download an office listing as PDF.

## Access

The route requires authentication and the manage-master-data permission.

## Output

The PDF contains office code, name, type and status. It includes active
and inactive offices and does not apply the office-page search filter.

## Tests

    php artisan test --filter=OfficePdfExportTest
    php artisan test
    npm run build
    composer audit

The tests check access, PDF download and the view's office details.
The downloaded document must also be checked visually in the browser.

## Remaining work

- Add the approved office Excel import workflow.
- Review filtering requirements for exports.
- Verify document layout and fonts for any additional languages.
