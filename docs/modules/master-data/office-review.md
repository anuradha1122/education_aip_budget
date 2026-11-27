# Offices — development review

## Implemented

The Offices module supports listing and searching, creating and editing,
unique office codes, and setting an office inactive without deleting it.
Changes are recorded in office audit history. The module also provides an
Excel import template, validation and reviewed import, Excel export and PDF
export. Import rechecks codes and rejects the import when a conflict is found.

The organisational-data gate allows system administrators and eligible
province-wide provincial administrators to manage offices. Zonal administrators
and subject directors are not granted office management. Access is checked on
the server, including direct routes.

## Evidence reported by the developer

- The full application suite passed 616 tests and 4,309 assertions before this
  documentation-only update. The suite included Office and provincial
  master-data access tests.
- The developer reported successful system-administrator browser checks for
  office creation, search, editing, duplicate-code rejection, deactivation,
  audit history, and Excel and PDF downloads.
- The developer reported successful browser checks of the requested Office role
  access boundaries.

## Remaining acceptance

- Confirm official office names and codes with the department.
- Review the effect of office deactivation and later reactivation on all
  dependent workflows as those workflows are implemented. Test historical
  references in the complete end-to-end workflow.
- Verify the import/export process with representative approved office data
  and complete production-environment and user acceptance checks. Automated
  tests exist, but this review does not claim that a representative production
  workbook has been accepted.

Older Office documentation may describe earlier stages and older gate names;
use this review alongside the current routes and tests when assessing status.
