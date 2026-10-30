# First administrator setup

## Purpose

Create the first administrator account for local development.

## Requirements

- The application environment must be local.
- The user and administrator migrations must have run.
- No administrator account may already exist.
- The selected email must not already belong to another user.

## Command

    php artisan db:seed --class=FirstUserSeeder

The command asks for a name, email and password. Password entry is hidden.

The password is stored as a hash. Do not commit credentials to Git or
include them in documentation.

## Restrictions

- The seeder refuses to run outside the local environment.
- It does not promote an existing user.
- It refuses to create another account when an administrator exists.
- It is intended for controlled local setup, not production deployment.

## Tests

    php artisan test --filter=FirstAdministratorSeederTest
    php artisan test

The tests check account creation, the existing-administrator restriction,
and the environment restriction.

## Remaining work

- Define an approved production administrator setup procedure.
- Add administrator-managed user creation and access changes.
- Record access changes in the audit history.
