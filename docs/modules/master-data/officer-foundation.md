# Officer foundation

The officers table stores an officer's name, current office, current
designation, and active status. Each officer must reference an existing
office and designation.

The officer service trims the name and validates required fields. Officers
can be marked inactive without deleting their records. The service checks
that the office and designation exist; it does not currently restrict
selection based on their active status.

## Audit recording

The service records officer creation and updates, including the actor when
provided and the previous and new values. An unchanged officer does not
produce an update event. The change and audit event are saved in the same
transaction.

The table does not create or link login accounts. Management screens, an
audit history page, and assignment history are future work.

## Testing

    php artisan test --filter=OfficerDatabaseTest
    php artisan test --filter=OfficerServiceTest
    php artisan test --filter=OfficerAuditTest