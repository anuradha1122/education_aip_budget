# M05: AIP activity register

## Status

**In progress.** This document records the current implementation and
outstanding work for the AIP activity register.

M05 must not be considered complete until the remaining features, checks,
documentation and acceptance testing have been completed.

## Purpose

The AIP activity register maintains annual activities for the Sabaragamuwa
Province Programme Budget system.

An activity is identified by its activity number within a financial year.
The same activity may be used by multiple offices.

## Confirmed requirements

### Activity numbers

- An activity number is unique within a financial year.
- The same number may be used again in another financial year.
- New activity numbers must contain digits only.
- Activity numbers are stored as text so that leading zeros, such as `001`,
  are preserved.
- One common activity number is used across the province and zones for the
  same activity in a financial year.

### Programme level

- Programme level describes whether the programme is held at provincial,
  zonal or school level.
- Programme level is separate from the physical location or venue.

### Offices, subjects and officers

- Each office has its own subjects and officers.
- An officer may be assigned to multiple subjects in the same office.
- A subject may have multiple officers in the same office.
- A common activity may be linked to multiple offices.
- Each office has its own allocation and spending for an activity. The
  allocation and spending features are not yet implemented.

### Votes and funds

- A vote may be used by multiple activities.
- An activity may have multiple funds and vote entries.
- Votes and funds may be the same or different across activities.
- Votes and funds are assigned manually in the AIP data. The system must
  not assume that one fund always has one vote.

### Annual AIP import

The corrected AIP format contains these ten columns:

1. Serial number
2. Level
3. Activity Number
4. Activity
5. Subject code
6. Subject
7. Officer
8. Designation
9. Vote
10. Fund

The user will select the financial year when importing.

Training and meeting are programme types, not funds. The corrected AIP
format does not have a programme-type column.

## Implemented

- Authorised users can upload an AIP workbook for preliminary validation.
  The page checks the selected financial year, template headings, activity
  numbers and descriptions, and displays the results without saving rows.

### Activity register

- `aip_activities` stores an activity number, description, optional
  programme level and optional physical location.
- Each activity references an existing financial year.
- The database enforces activity-number uniqueness within a financial year.
- `AipActivityService` validates and trims basic activity details.
- The creation service checks that the financial year exists and is open.
- The creation service validates new activity numbers as digits only.
- Activity creation through the service records an audit event in the same
  transaction. The actor is recorded when provided.
- Users with `manage-master-data` permission can create and search
  activities through the AIP activity page.
- The activity creation page passes the signed-in user to the service.
- The activity form resets after a successful save.
- An audit history page displays recorded activity creation events to users
  with `manage-master-data` permission.
- An AIP activities link is available in the sidebar for users with the
  relevant permission.

### Subjects and officers

- An activity can optionally reference a subject and an officer.
- If either is supplied to the creation service, both are required.
- The service checks that the subject and officer belong to the same office
  and that the officer has a current assignment to that subject.
- The subject and officer IDs are included in the activity creation audit.
- `officer_subject_assignments` stores officer–subject assignments.
- The assignment service checks same-office membership and rejects
  duplicate current assignments.
- Authorised users can create and list assignments.
- An assignment can be ended without deleting its record. Ending it records
  an audit event.
- A previously ended pair can be assigned again as a new record.

### Activity–office links

- `aip_activity_offices` links a common activity to multiple offices.
- The same office cannot be linked twice to the same activity.
- `AipActivityOfficeService` checks that the activity and office exist and
  prevents link creation for a closed financial year.
- Link creation through the service records an audit event in the same
  transaction.
- Users with `manage-master-data` permission can create and view links.
  The page passes the signed-in user to the service and resets after a
  successful link.

### Vote and fund entries

- `aip_activity_funding_entries` can link an activity to multiple vote and
  fund entries.
- The referenced activity, vote and fund must exist.
- `AipActivityFundingEntryService` checks the references and rejects new
  entries for a closed financial year.
- The service records a creation audit event in the same transaction.
- Users with `manage-master-data` permission can assign votes and funds
  and view existing entries. The page passes the signed-in user to the
  service.

### Import template

- Users with `manage-master-data` permission can download a blank AIP
  import template with the ten columns in the corrected format.
- The template was downloaded and compared with the corrected format in the
  browser.

## Tests and checks completed

The following checks have passed during development:

- Activity database, service, creation, search and audit tests.
- Activity-number uniqueness tests and digit-only validation test.
- Separate programme-level and physical-location test.
- Officer–subject assignment, ending and audit tests.
- Activity–office database, service, management and audit tests.
- Activity funding-entry database, service, management and audit tests.
- Import-template download and permission tests.
- TypeScript checks and production builds for the pages developed so far.
- Browser checks for activity creation, activity–office links, vote and fund
  entries, and template download.

These results apply to the versions tested during development. The full M05
test suite and acceptance testing must be run again for the completed module.

- The preliminary AIP import review displays all ten template columns,
  the Excel row number and preliminary validation errors.

- Preliminary AIP validation checks whether supplied vote and fund codes
  exist in master data and reports unknown codes by row.

- The preliminary AIP import page requires the user to select a financial
  year and an office, and displays both with the review results.

## Not yet implemented or confirmed

### Annual import

- Subject, officer and designation matching and reviewed saving are not
  yet implemented. Blank vote and fund values and inactive references are
  not yet rejected. The import must not silently discard vote or fund values.
- Vote and fund validation should be tested with a full-size annual AIP
  workbook and improved if necessary.

### Activity and link corrections

- Activity update actions and update audit recording are not yet implemented.
- Correction or ending of activity–office links is not yet implemented.
- Funding-entry correction and an audit history page are not yet implemented.
- The rules for duplicate activity–vote–fund entries still need confirmation.
  The current funding-entry service allows repeated combinations.
- Rules for selecting inactive offices, votes and funds still need confirmation.

### Financial-year and access controls

- Closed-year checks exist in the current creation services, but are not
  enforced across every future AIP action or direct database write.
- The financial-year closing and reopening workflow, including concurrent
  changes, needs review.
- Final AIP permissions, roles and office-scoped access need confirmation
  and testing.
- English-only validation for AIP activity descriptions is not yet enforced.

### Historical data

- Existing `location` values entered before level and physical location were
  separated need individual review.
- Existing invalid activity numbers, including the test value `x1`, are not
  automatically corrected by the new validation.
- Assignment records created before assignment audit recording do not have
  reconstructed audit events.
- Activity–office links and funding entries created before their respective
  audit recording do not have reconstructed audit events.
- Effective dates and a link from an activity to a specific officer–subject
  assignment period are not yet implemented.
- Officer or subject transfers between offices still need handling.
- The assignment list shows the officer's current office; it is not a
  complete historical office display.

### Future budget work

- Office-specific allocations and expenditure have not been implemented.
- The relationships between office allocations, funding entries and
  expenditure require further design.
- AIP reporting and reconciliation remain to be completed.

## Dependencies and completion

M05 depends on organisation and master data, permissions and financial-year
controls. Those dependencies must be reviewed as part of module completion.

Before M05 is reported as complete:

- Complete the agreed M05 features and resolve or document outstanding rules.
- Test permissions, office access and closed-year behavior.
- Test the annual import with valid, invalid and duplicate data.
- Complete relevant audit and correction checks.
- Run the relevant automated tests and browser acceptance checks.
- Review the module documentation and prepare the completion report.

- The selected office is not yet used for subject or officer matching or
  saved with an import. Existing common activities are still reported as
  duplicates, so separate office files cannot yet be imported.
- The provincial main administrator must be able to upload separate AIP
  files for all eight offices. Final office-scoped permissions remain to be
  implemented and tested.