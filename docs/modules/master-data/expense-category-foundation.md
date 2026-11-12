# Expense category foundation

The expense_categories table stores an expense category code, name, and
active status. Category codes are unique. Categories can be marked inactive
without deleting their records.

The expense category service trims names, converts codes to uppercase,
validates required fields, and rejects duplicate codes.

Management screens and audit recording are future work.

## Testing

    php artisan test --filter=ExpenseCategoryDatabaseTest
    php artisan test --filter=ExpenseCategoryServiceTest