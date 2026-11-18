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
- One common activity number is used across offices for the same activity
  in a financial year. A different provincial activity has a different number.

### Programme level

- Programme level describes whether the programme is held at provincial,
  zonal or school level.
- Programme level is separate from the physical location or venue.

### Offices, subjects and officers

- Each office has its own subjects and officers.
- An officer may be assigned to multiple subjects in the same office.
- A subject may have multiple officers in the same office.
- A common activity may be linked to multiple offices.
- Each office has its own responsible officer, allocation and spending.
  Allocation and spending features are not yet implemented.
- Provincial activities may differ from zonal activities; different activities
  have different numbers within the same financial year.

### Votes and funds

- A vote may be used by multiple activities.
- Each activity has one fund shared by all offices linked to it.
- Offices share the vote for a common activity. Different activities may
  have different funds or votes.
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

The provincial main administrator will upload eight separate files: one for
the province and one for each of seven zones. The administrator selects the
financial year and office for each file. An activity number appears at most
once within an office workbook.

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
- The officer–subject assignments page is linked from the sidebar for users
  with the relevant permission.

### Activity–office links

- `aip_activity_offices` links a common activity to multiple offices.
- The same office cannot be linked twice to the same activity.
- `AipActivityOfficeService` checks that the activity and office exist and
  prevents link creation for a closed financial year.
- Link creation through the service records an audit event in the same
  transaction.
- Office links have optional office-specific subject and officer fields. If
  either is supplied to the service, both are required; both must belong to
  the selected office and have an active assignment. The IDs are audited.
- Existing activity-level subject and officer fields have not been migrated
  to office links, and the link page and import do not yet use the new fields.
- Users with `manage-master-data` permission can create and view links.
  The page passes the signed-in user to the service and resets after a
  successful link.

### Vote and fund entries

- `aip_activity_funding_entries` stores activity, vote and fund entries.
  Its existing database schema still permits conflicting funds and duplicates.
- The referenced activity, vote and fund must exist.
- `AipActivityFundingEntryService` checks the references and rejects new
  entries for a closed financial year.
- The service records a creation audit event in the same transaction.
- The funding service rejects a different fund for an activity that already
  has one; a rejected entry creates no funding entry or audit event. Direct
  database writes are not protected by this service check.
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
- Preliminary review checks supplied subject codes and officer names against
  the selected office, compares supplied designations, and checks active
  officer–subject assignments when both records are uniquely matched.
- Office-specific subject and officer service checks passed for two offices,
  incorrect offices, missing or ended assignments, and audit recording.
- The one-fund service test and funding regression tests passed. A read-only
  check of the development database found no activities with multiple funds.
  This result applies only to that database at the time of the check.

## Not yet implemented or confirmed

### Annual import

- Reviewed saving is not implemented. Preliminary checks do not establish
  that a workbook is ready to save. Blank fields, subject names and inactive
  references still need agreed validation rules. The import must not silently
  discard vote or fund values.
- Existing activities are flagged for review; reuse across office files has
  not been implemented. Imported activity–office links, office-specific
  assignments and funding entries are not yet saved.
- Preliminary review reports known fund or vote values that conflict with
  existing funding entries. The reviewed save workflow must revalidate the
  common activity and its funding and handle conflicts without overwriting
  existing data.
- Vote and fund validation should be tested with a full-size annual AIP
  workbook and improved if necessary.

### Activity and link corrections

- Activity update actions and update audit recording are not yet implemented.
- Correction or ending of activity–office links is not yet implemented.
- Funding-entry correction and an audit history page are not yet implemented.
- The service rejects different funds for one activity, but the database does
  not yet enforce one fund per activity. Repeated combinations are still
  possible; correction and duplicate-handling rules need implementation.
- Rules for selecting inactive offices, votes and funds still need confirmation.

### Financial-year and access controls

- Closed-year checks exist in the current creation services, but are not
  enforced across every future AIP action or direct database write.
- The financial-year closing and reopening workflow, including concurrent
  changes, needs review.
- Provincial main administrators must be able to view all offices; zonal
  users must see only their own office and subject directors their own records.
  These access rules and their tests are not yet implemented.
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
  expenditure require further design. Estimates are records and do not reduce
  allocations. Final actual expenditure after the programme reduces the
  corresponding office allocation. The accounts branch handles payment.
- Zonal subject directors prepare estimates; zonal administrators can approve
  them. These roles and workflows require implementation and testing.
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
