# Fund foundation

## Purpose

Store fund reference records for use by the programme budget system.

## Fields

- `id`: internal identifier.
- `code`: unique fund code, up to 50 characters.
- `name`: fund name, up to 255 characters.
- `is_active`: whether the fund is active.
- `created_at` and `updated_at`: record timestamps.

## Validation

The fund service trims spaces from the code and name and converts the
code to uppercase. Both fields are required. Codes must be unique.

A fund may be deactivated without deleting the record.

## Current scope

This step provides the database table, model, service and service tests.
It does not yet provide a management screen, audit history, Excel import
or export, or a PDF listing.

Official fund names and codes should be confirmed before entering them.

## Tests

    php artisan test --filter=FundServiceTest
    php artisan test
