# Vote master data foundation

## Purpose

Store vote codes and descriptions for use in programme budgets.

## Fields

- Code: required and unique.
- Description: required.
- Active status: indicates whether the vote is active.

## Validation

The vote service trims codes and descriptions and converts codes to
uppercase. Duplicate codes and empty descriptions are rejected.

Votes can be deactivated instead of deleted.

## Tests

    php artisan test --filter=VoteServiceTest
    php artisan test

## Remaining work

- Add audit history.
- Add administrator-protected management routes and a page.
- Add navigation and applicable import and export features.
- Protect historical references when related financial records are added.