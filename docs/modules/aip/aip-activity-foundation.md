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

- `officer_subject_assignments` stores officer–subject pairs.
- Through `OfficerSubjectAssignmentService`, an officer can be assigned
  multiple subjects and a subject can have multiple officers. The service
  checks that both records belong to the same office and rejects duplicate
  pairs.

- When a subject and officer are provided to `AipActivityService`,
  the service checks that they belong to the same office and that the
  officer is assigned to the subject. Both IDs are included in the
  creation audit event.

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

- An assignment user interface and assignment audit/history are not yet
  implemented. AIP update actions still need their own assignment checks.
- The AIP creation service currently permits both subject and officer to
  be omitted. Required fields for the final AIP workflow need confirmation.
- The effect of moving an officer or subject to another office still needs
  to be handled.

- A user interface, assignment audit/history, and AIP creation checks
  against officer–subject assignments are not yet implemented.
- The effect of moving an officer or subject to another office still needs
  to be handled; the same-office check currently applies when assigning
  through the service.