# Vote audit recording

The vote service records an audit event when a vote is created or changed.

Each event stores the vote, action, acting user when supplied, previous
values, new values and timestamps. Deactivation is recorded as an update.
An unchanged vote does not create an update event.

The vote change and its audit event are saved in a database transaction.

## Tests

    php artisan test --filter=VoteAuditTest
    php artisan test

## Remaining work

- Add administrator-protected vote management.
- Pass the authenticated user to the service from management actions.
- Add an administrator-protected audit history page.