# Office validation

## Scope

OfficeService validates office data before creating or updating an office.

## Fields

| Field | Rules |
| --- | --- |
| code | Required; maximum 30 characters; uppercase letters, numbers, underscore and hyphen; unique |
| name | Required; maximum 150 characters |
| type | Required; provincial or zonal |
| is_active | Required; boolean |

Office codes and names are trimmed. Codes are converted to uppercase.

## Deactivation

An office can be marked inactive without deleting its record.

## Authorisation

OfficeService does not authorise users. Any controller or other entry point
calling the service must check the appropriate permission first.

Public user registration is disabled. User account management will be
handled in the access-control module.

## Outputs and notifications

Excel import/export and PDF listings are planned for the master-data
module. They are not implemented in this part.

Notifications and email are not specified for this module in the
current requirements workbook.

## Tests

Run:

    php artisan test --filter=Office
    php artisan test

The tests cover creation, normalisation, duplicate codes, invalid office
types, editing and deactivation.

## Remaining work

- Define and implement administrative permissions.
- Add authorised office screens and routes.
- Add search and filters.
- Add audit history.
- Implement applicable Excel and PDF outputs.
- Test historical references when related records are introduced.
