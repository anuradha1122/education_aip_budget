# Vote import template

Administrators can download a blank Excel template from the vote
management page.

The template contains three columns:

- Vote code
- Vote description
- Status

The download route requires authentication and the
manage-master-data permission.

## Tests

    php artisan test --filter=VoteImportTemplateTest
    php artisan test
    npx tsc --noEmit
    npm run build

## Remaining work

- Validate completed workbooks and display errors.
- Import reviewed, valid votes and record audit events.