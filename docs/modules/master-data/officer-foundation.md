# Officer foundation

The officers table stores an officer's name, current office, current
designation, and active status. Each officer must reference an existing
office and designation.

The officer service trims the name and validates required fields. Officers
can be marked inactive without deleting their records.

The service currently checks that the office and designation exist; it does
not restrict selection based on their active status. The table does not
create or link login accounts.

Management screens, audit recording, and assignment history are future work.

## Testing

    php artisan test --filter=OfficerDatabaseTest
    php artisan test --filter=OfficerServiceTest