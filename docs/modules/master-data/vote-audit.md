# Vote audit recording

The vote service records an audit event when a vote is created or changed.

Each event stores the vote, action, acting user when supplied, previous
values, new values and timestamps. Deactivation is recorded as an update.
An unchanged vote does not create an update event.

The vote change and its audit event are saved in a database transaction.

## Tests

    php artisan test --filter=VoteAuditTest
    php artisan test

## Audit history page

Administrators can open vote audit history from the vote management page.
The page displays the acting user, action, recorded time, and previous and
new values. Access requires the manage-master-data permission.

The vote heading displays the current vote code and description. The
previous and new values show the details recorded for each event.

## Remaining work

- Add administrator-protected vote management.
- Pass the authenticated user to the service from management actions.