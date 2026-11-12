# Expense category management

Users with master data management permission can create, search, edit,
activate, and deactivate expense categories. Each category has a unique
code, name, and status.

The service trims names, converts codes to uppercase, validates required
fields, and rejects duplicate codes. Deactivation retains the record.

## Audit recording

The service records category creation and updates, including the actor
when provided and the previous and new values. An unchanged category does
not produce an update event. The change and audit event are saved in the
same transaction.

Sidebar navigation and an audit history page are future work.

## Testing

    php artisan test --filter=ExpenseCategoryDatabaseTest
    php artisan test --filter=ExpenseCategoryServiceTest
    php artisan test --filter=ExpenseCategoryAuditTest
    php artisan test --filter=ExpenseCategoryManagementTest
    npx tsc --noEmit
    npm run build