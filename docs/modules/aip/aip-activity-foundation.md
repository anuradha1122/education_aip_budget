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

- `AipActivityService` validates and trims basic activity fields.
- The creation service checks that the financial year exists and is open.
  This check currently applies only to creation through this service.

- Activity creation through `AipActivityService` records an audit event
  in the same database transaction. The event includes the actor when
  provided and the created values.

- AIP activities can optionally reference an existing subject and officer
  at the database and model level.

## Not yet implemented

- The activity-number uniqueness rule is awaiting confirmation.
- The database foundation does not prevent changes for a closed year.
- English validation, permissions and office scoping are not yet added.
- Subject, officer, designation, vote, fund and office relationships are
  not yet added.
- AIP entry, import, allocations, audit history and reports are not yet
  implemented.

- Closed-year restrictions are not yet enforced across all AIP actions.
  The year-closing workflow and concurrent changes still need review.

- Audit recording for updates and an audit history page are not yet
  implemented. The future AIP interface must pass the authenticated actor.

- Officer–subject assignments and same-office validation are not yet
  implemented. The AIP creation service does not yet save or validate
  subject and officer references.
- Vote, fund and activity office relationships remain to be designed.