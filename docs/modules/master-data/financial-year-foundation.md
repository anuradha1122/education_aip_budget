# Financial year foundation

The financial_years table stores the year, start date, end date and
closed status. Year numbers are unique, and a new year defaults to open.

## Tests

    php artisan test --filter=FinancialYearTest
    php artisan test

## Creation validation

The financial year service requires a unique year and valid start and
end dates. The end date cannot be before the start date. New financial
years are created open.

A department-specific calendar and overlap rules have not yet been
established.

## Remaining work

- Add protected routes and a management page.
- Record audit events for changes.
- Implement controlled closing and reopening.
- Enforce read-only restrictions on closed years when financial records
  and operations are added.
- Confirm the department's official financial calendar and rules.