# Fund audit history

## Purpose

The fund audit history page displays fund creation and update events
recorded by the fund service.

## Access

The `/fund-audit` route requires authentication and the
`manage-master-data` permission.

## Display

Each event shows the current fund code and name, action, user, recorded
time, previous values and new values. Events are shown newest first,
with 20 events per page.

An unavailable user is displayed as "Unavailable".

## Testing

    php artisan test --filter=FundAuditHistoryTest
    php artisan test
    npm run build

