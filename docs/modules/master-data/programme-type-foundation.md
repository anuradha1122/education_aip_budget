# Programme type foundation

The programme_types table stores a programme type code, name, and active
status. Programme type codes are unique. Programme types can be marked
inactive without deleting their records.

The programme type service trims names, converts codes to uppercase,
validates required fields, and rejects duplicate codes.

Management screens and audit recording are future work.

## Testing

    php artisan test --filter=ProgrammeTypeDatabaseTest
    php artisan test --filter=ProgrammeTypeServiceTest