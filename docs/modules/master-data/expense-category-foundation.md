# Expense category foundation

The expense_categories table stores an expense category code, name, and
active status. Category codes are unique. Categories can be marked inactive
without deleting their records.

The expense category service trims names, converts codes to uppercase,
validates required fields, and rejects duplicate codes.

## Audit recording

The service records category creation and updates, including the actor when
provided and the previous and new values. An unchanged category does not
produce an update event. The change and audit event are saved in the same
transaction.

A management page and an audit history page are future work.

## Testing

    php artisan test --filter=ExpenseCategoryDatabaseTest
    php artisan test --filter=ExpenseCategoryServiceTest
    php artisan test --filter=ExpenseCategoryAuditTest