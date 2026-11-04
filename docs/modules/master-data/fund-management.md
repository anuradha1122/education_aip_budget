# Fund management

## Access

The `/funds` routes require authentication and the
`manage-master-data` permission.

## Functions

An administrator can:

- Create a fund.
- Search by code or name.
- Edit a fund.
- Activate or deactivate a fund.

Fund codes are normalised and must be unique. Deactivation retains the
record for future historical references.

## Audit

Fund creation and changes made through the fund service produce audit
events containing the actor, action and old and new values. Fund changes
and audit inserts are saved in one database transaction.

An audit-history screen has not yet been implemented.

## Remaining work

- Add the Funds link to the permission-aware sidebar.
- Add an audit-history screen.
- Add applicable Excel import and export and PDF output.
- Confirm official fund codes and names before entering production data.

## Tests

    php artisan test --filter=FundManagementTest
    php artisan test
    npm run build
