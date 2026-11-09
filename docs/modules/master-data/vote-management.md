# Vote management

Administrators can view, search, create, edit and deactivate votes.

The management routes require authentication and the
manage-master-data permission. Vote changes are recorded in the audit
table with the authenticated user.

## Tests

    php artisan test --filter=VoteManagementTest
    php artisan test
    npx tsc --noEmit
    npm run build

## Remaining work

- Add a permission-aware sidebar link.
- Add a vote audit history page.
- Add applicable Excel and PDF features.