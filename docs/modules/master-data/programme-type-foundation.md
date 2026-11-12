# Programme type management

Users with master data management permission can create, search, edit,
activate, and deactivate programme types. Each type has a unique code,
name, and status.

The service trims names, converts codes to uppercase, validates required
fields, and rejects duplicate codes. Deactivation retains the record.

## Audit recording

The service records programme type creation and updates, including the
actor when provided and the previous and new values. An unchanged type
does not produce an update event. The change and audit event are saved in
the same transaction.

The management page is available through the sidebar to users with master
data management permission. An audit history page is future work.

## Testing

    php artisan test --filter=ProgrammeTypeDatabaseTest
    php artisan test --filter=ProgrammeTypeServiceTest
    php artisan test --filter=ProgrammeTypeAuditTest
    php artisan test --filter=ProgrammeTypeManagementTest
    npx tsc --noEmit
    npm run build