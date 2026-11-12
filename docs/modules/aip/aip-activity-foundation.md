# AIP activity database foundation

## Implemented

- `aip_activities` stores an activity number, description and optional
  location/level.
- Each activity references an existing financial year.
- The activity number is stored as text to preserve values such as `001`.
- The database prevents deletion of a financial year referenced by an
  activity.
- Database tests cover saving an activity with a financial year and
  rejecting a nonexistent financial year.

## Not yet implemented

- The activity-number uniqueness rule is awaiting confirmation.
- The database foundation does not prevent changes for a closed year.
- English validation, permissions and office scoping are not yet added.
- Subject, officer, designation, vote, fund and office relationships are
  not yet added.
- AIP entry, import, allocations, audit history and reports are not yet
  implemented.