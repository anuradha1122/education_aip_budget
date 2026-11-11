# Designation foundation

The designations table stores a designation code, name, and active status.
Designation codes are unique. Designations can be marked inactive without
deleting their records.

The designation service trims names, converts codes to uppercase, validates
required fields, and rejects duplicate codes.

## Audit recording

The service records designation creation and updates, including the actor
when provided and the previous and new values. An unchanged designation
does not produce an update event. Audit recording and the corresponding
change are saved in the same transaction.

Management screens, audit history display, and officer relationships are
future work.

## Testing

    php artisan test --filter=DesignationDatabaseTest
    php artisan test --filter=DesignationServiceTest
    php artisan test --filter=DesignationAuditTest