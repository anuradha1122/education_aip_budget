# Administrator access foundation

## Purpose

Provide an initial permission check for master-data management.

## Data

The users table has an is_admin boolean field.

- The default is false.
- The field is not included in the User model's mass-assignable fields.
- An administrator assignment must use a controlled setup or management
  procedure.

## Permission

The manage-master-data gate allows a user whose is_admin field is true.

Future routes and controllers must explicitly check this permission.
The gate alone does not protect an endpoint.

## Registration

Public registration remains disabled.

## Tests

Run:

    php artisan test --filter=MasterDataAccessTest
    php artisan test

The tests check the default value and gate decisions for an administrator,
a regular user and a guest.

## Remaining work

- Create a controlled initial administrator setup.
- Define detailed roles and permissions.
- Implement office assignments and office-level access restrictions.
- Apply permission checks to routes, controllers, reports and exports.
- Add audit history for account and permission changes.
- Add user-management screens and appropriate notifications.
