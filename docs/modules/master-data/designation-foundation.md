# Designation foundation

The designations table stores a designation code, name, and active status.
Designation codes are unique. Designations can be marked inactive without
deleting their records.

This step provides the database table and model. Validation, management
screens, audit history, and officer relationships are future work.

## Testing

    php artisan test --filter=DesignationDatabaseTest