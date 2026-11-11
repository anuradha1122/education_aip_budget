# Designation management

Administrators can create, search, edit, activate, and deactivate
designations. Each designation has a unique code, name, and status.

The service trims names, converts codes to uppercase, validates required
fields, and rejects duplicate codes. Deactivation retains the record.

## Audit recording

The service records designation creation and updates, including the actor
when provided and the previous and new values. An unchanged designation
does not produce an update event. The change and audit event are saved in
the same transaction.

The management page is available to users with master data management
permission. Sidebar navigation and an audit history page are future work.

## Testing

    php artisan test --filter=DesignationDatabaseTest
    php artisan test --filter=DesignationServiceTest
    php artisan test --filter=DesignationAuditTest
    php artisan test --filter=DesignationManagementTest
    npx tsc --noEmit
    npm run build