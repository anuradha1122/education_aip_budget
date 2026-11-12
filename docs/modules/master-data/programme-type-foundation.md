# Programme type foundation

The programme_types table stores a programme type code, name, and active
status. Programme type codes are unique. Programme types can be marked
inactive without deleting their records.

This step provides the database table and model. Validation, management
screens, and audit recording are future work.

## Testing

    php artisan test --filter=ProgrammeTypeDatabaseTest