# Subject master data foundation

A subject belongs to an office and has a code, name and active status.
The database prevents deletion of an office while subjects refer to it.

## Code rule

A subject code is currently unique within an office. The same code can
be used by different offices. This rule is an implementation assumption
and needs confirmation from the department.

## Tests

    php artisan test --filter=SubjectDatabaseTest
    php artisan test
    php artisan test --filter=SubjectServiceTest
    php artisan test --filter=SubjectAuditTest

## Validation service

The subject service requires an existing office, a code, a name and an
active status. It trims the code and name and converts the code to
uppercase. It checks that a code is unique within the selected office.

The service supports updating and deactivating a subject.

## Audit recording

The subject service records creation and update events, including
deactivation. Events contain the subject, acting user when supplied,
previous and new values, and timestamps.

The subject change and audit event are saved in one database transaction.
An unchanged subject does not create an update event.

## Remaining work

- Add an administrator-protected audit history page.
- Add protected subject management.
- Define responsible officers and designations.
- Implement office-level access restrictions.