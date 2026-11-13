# AIP activity database foundation

## Implemented

- `aip_activities` stores an activity number, description and optional
  location/level. Each activity references an existing financial year.
- The activity number is stored as text to preserve values such as `001`.
  The database prevents deletion of a financial year referenced by an activity.
- `AipActivityService` validates and trims basic activity fields and checks
  that the selected financial year exists and is open during creation.
- Activity creation through the service records an audit event in the same
  transaction, including the created values and the actor when provided.
- An activity may optionally reference a subject and an officer. If one is
  provided to the creation service, the other is required. The service checks
  that they belong to the same office and that the officer is assigned to the
  subject. Both IDs are included in the creation audit event.
- `officer_subject_assignments` stores officer–subject pairs. One officer can
  have multiple subjects and a subject can have multiple officers. The
  assignment service checks same-office membership and rejects duplicate pairs.
- New assignments through the service produce an audit event in the same
  transaction, including the officer, subject, office and actor when provided.
- Tests cover the database references, creation validation, activity and
  assignment audit events, same-office checks, duplicate assignments and
  the multiple-officer/multiple-subject cases.

- Administrators with `manage-master-data` permission can create and list
  officer–subject assignments.
- An assignment can be ended while retaining its record. The end action
  records an audit event with the actor when provided.
- A previously ended officer–subject pair can be assigned again as a new
  record. Only one current record for the same pair is allowed.
- AIP activity creation accepts a selected officer–subject pair only when
  a current assignment exists.

- An initial AIP activity page lets users with `manage-master-data`
  permission create and search activities. It offers open financial years
  and current officer–subject assignments.
- Activity creation uses the existing validation service and records the
  authenticated user in the creation audit event.

## Not yet implemented or confirmed

- The activity-number uniqueness rule is awaiting confirmation.
- Closed-year checks are not enforced across all AIP actions or direct
  database writes. The year-closing workflow and concurrent changes need
  review.
- English validation, final permissions and office-scoped access are not
  yet implemented for AIP activities.
- The creation service currently permits both subject and officer to be
  omitted. Required fields for the final AIP workflow need confirmation.
- Vote, fund and activity office relationships still need design. An officer's
  designation is linked through the existing officer record.
- AIP entry and import, allocations, update audit recording, audit history
  pages and reports are not yet implemented.
- The officer–subject assignment interface, removal and transfer handling,
  and historical validity rules are not yet implemented. Office changes can
  affect existing assignments; the creation checks alone do not prevent this.
- Future user-facing activity and assignment actions must pass the
  authenticated actor. The service APIs currently permit a missing actor.
- Existing assignments made before assignment audit recording was added do
  not have reconstructed audit events.

- Effective dates and a link from an AIP activity to a specific assignment
  period are not yet implemented.
- Officer or subject transfers between offices still need handling.
- The assignment list shows the officer's current office; it is not a
  complete historical office display.
- The final assignment and AIP permissions still need confirmation.