# Office management

## Scope

Administrators can view, search, create, edit and deactivate offices.

## Routes

| Method | Path | Action |
| --- | --- | --- |
| GET | /offices | List and search offices |
| GET | /offices/{office}/edit | Open the edit form |
| POST | /offices | Create an office |
| PUT | /offices/{office} | Update an office |

All routes require authentication and the manage-master-data permission.

## Validation

OfficeService validates and normalises office details. Office codes
must be unique.

## Deactivation

Set an office to inactive instead of deleting it. The current
implementation does not provide an office deletion route.

## Tests

    php artisan test --filter=OfficeManagementTest
    php artisan test
    npm run build

Test coverage includes guest and regular-user denial, administrator
access, creation, duplicate validation and deactivation.

## Remaining work

- Add permission-aware navigation.
- Add audit history.
- Protect and test historical references as related records are added.
- Implement applicable Excel import/export and PDF listing.
- Confirm and enter official office names and codes.
