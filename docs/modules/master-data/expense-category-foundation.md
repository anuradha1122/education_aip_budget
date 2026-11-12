# Expense category foundation

The expense_categories table stores an expense category code, name, and
active status. Category codes are unique. Categories can be marked inactive
without deleting their records.

This step provides the database table and model. Validation, management
screens, and audit recording are future work.

## Testing

    php artisan test --filter=ExpenseCategoryDatabaseTest