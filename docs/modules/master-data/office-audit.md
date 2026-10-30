# Office audit history

## Purpose

Record office creation and updates.

## Stored information

Each event stores:

- Office ID
- Actor ID, when available
- Action: created or updated
- Previous office values for an update
- New office values
- Event time

## Behaviour

Office changes and audit events are saved in the same database transaction.
An unchanged update does not create an event.

OfficeService records events when it is used. Future code must use the
service for office changes or otherwise provide equivalent audit handling.

## Access

An audit viewing screen is not implemented yet. Direct database access
must be restricted to authorised administrators.

## Tests

    php artisan test --filter=OfficeAuditTest
    php artisan test --filter=Office
    php artisan test

## Remaining work

- Provide an authorised audit-history screen.
- Define retention, protection and export requirements.
- Extend audit coverage to other master data and system actions.
