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
