# Programme type foundation

The programme_types table stores a programme type code, name, and active
status. Programme type codes are unique. Programme types can be marked
inactive without deleting their records.

The programme type service trims names, converts codes to uppercase,
validates required fields, and rejects duplicate codes.

## Audit recording

The service records programme type creation and updates, including the
actor when provided and the previous and new values. An unchanged type
does not produce an update event. The change and audit event are saved in
the same transaction.

Management screens and an audit history page are future work.

## Testing

    php artisan test --filter=ProgrammeTypeDatabaseTest
    php artisan test --filter=ProgrammeTypeServiceTest
    php artisan test --filter=ProgrammeTypeAuditTest