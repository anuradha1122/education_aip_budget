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

## Creation audit

Creating a financial year through the service records a creation event
with its values and the acting user when supplied. The year and audit
event are saved in one database transaction.

Audit events for closing and reopening remain to be implemented.

## Management page

Administrators can view and create financial years at /financial-years.
The routes require authentication and the manage-master-data permission.

The page passes the signed-in user to the service so creation is recorded
in the financial year audit table.

## Management tests

    php artisan test --filter=FinancialYearManagementTest
    php artisan test
    npx tsc --noEmit
    npm run build

## Editing and audit history

Administrators can edit the year number and dates of an open financial
year. The server rejects edits to closed years.

Changes are recorded with the acting user and previous and new values.
An unchanged year does not create an update event. Administrators can
view the recorded events from the financial years page.

## Tests

    php artisan test --filter=FinancialYearEditTest
    php artisan test --filter=FinancialYearAuditHistoryTest
    php artisan test
    npx tsc --noEmit
    npm run build

## Remaining work

- Record audit events for changes.
- Implement controlled closing and reopening.
- Enforce read-only restrictions on closed years when financial records
  and operations are added.
- Confirm the department's official financial calendar and rules.