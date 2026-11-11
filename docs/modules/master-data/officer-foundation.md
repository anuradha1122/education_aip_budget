# Officer foundation

The officers table stores an officer's name, current office, current
designation, and active status. Each officer must reference an existing
office and designation.

Officers can be marked inactive without deleting their records. The table
does not create or link login accounts.

This step provides the database table and model. Validation, management,
audit recording, and assignment history are future work.

## Testing

    php artisan test --filter=OfficerDatabaseTest