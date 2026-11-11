# Officer management

Users with master data management permission can create, search, edit,
activate, and deactivate officers. Each officer has a name, current office,
current designation, and active status.

The officer service trims the name and validates required fields. The office
and designation must exist. Inactive offices and designations are currently
available for selection and are labelled inactive.

## Audit recording

The service records officer creation and updates, including the actor when
provided and the previous and new values. An unchanged officer does not
produce an update event. The change and audit event are saved in the same
transaction.

The table does not create or link login accounts. The management page is
available through the sidebar to users with master data management
permission. An audit history page and assignment history are future work.

## Testing

    php artisan test --filter=OfficerDatabaseTest
    php artisan test --filter=OfficerServiceTest
    php artisan test --filter=OfficerAuditTest
    php artisan test --filter=OfficerManagementTest
    npx tsc --noEmit
    npm run build