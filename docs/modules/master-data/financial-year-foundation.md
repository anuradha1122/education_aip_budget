# Financial year foundation

The financial_years table stores the year, start date, end date and
closed status. Year numbers are unique, and a new year defaults to open.

## Tests

    php artisan test --filter=FinancialYearTest
    php artisan test

## Remaining work

- Validate dates and manage financial years through protected routes.
- Record audit events for changes.
- Implement controlled closing and reopening.
- Enforce read-only restrictions on closed years when financial records
  and operations are added.
- Confirm the department's official financial calendar and rules.