# Designation foundation

The designations table stores a designation code, name, and active status.
Designation codes are unique. Designations can be marked inactive without
deleting their records.

The designation service trims names, converts codes to uppercase, validates
required fields, and rejects duplicate codes.

Management screens, audit history, and officer relationships are future work.

## Testing

    php artisan test --filter=DesignationDatabaseTest
    php artisan test --filter=DesignationServiceTest