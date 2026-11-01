# Change log

## Initial development

- Added the offices table and model.
- Added office database tests.
- Added initial module documentation.

## Office validation service

- Added validation and normalisation for office creation and editing.
- Added service tests for duplicates, office type and deactivation.
- Updated registration tests to verify public registration is disabled.
- Documented outstanding authorisation and output work.

## Office management

- Added administrator-protected office routes and page.
- Added office creation, editing, search and deactivation.
- Added feature tests for permissions and office operations.
- Documented outstanding navigation, audit and output work.

## Office navigation

- Added an Offices sidebar link for users with master-data permission.
- Shared the permission result with the Inertia frontend.
- Added tests for administrator, regular-user and guest permission values.

## Office audit history

- Added audit events for office creation and updates.
- Recorded office changes and audit events in one transaction.
- Added tests for audit records and unauthorised creation.

## Office audit history screen

- Added an administrator-protected audit history page.
- Added pagination and before-and-after values.
- Added tests for administrator, regular-user and guest access.

## Office Excel export

- Added an administrator-protected Excel office list.
- Included active and inactive offices.
- Added access and workbook-content tests.

## Office import template

- Added an administrator-protected blank Excel template.
- Added access and template-content tests.
- Documented the remaining import work.
