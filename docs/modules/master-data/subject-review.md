# Subjects — development review

## Implemented

The Subjects module supports listing, creating, editing and deactivating
subjects without deleting their records. A subject belongs to an office; its
normalised code must be unique within that office. The same code may be used
in a different office. Changes are recorded in subject audit history.

The module provides an Excel import template, validation and reviewed import,
Excel export and PDF export. Import checks for invalid rows and duplicate
codes, and protects against an office-code change redirecting a reviewed import.

The organisational-data gate permits system administrators and eligible
province-wide provincial administrators to manage subjects. Zonal
administrators are not granted Subject management. Routes are protected on
the server.

## Evidence reported by the developer

- The full application suite passed 616 tests and 4,309 assertions before
  this documentation-only review. It included Subject and provincial
  master-data access tests.
- The developer reported successful browser checks for creating, editing and
  deactivating a test subject; same-office duplicate rejection; reusing a code
  in another office; audit history; Excel and PDF downloads; and the requested
  provincial and zonal role boundaries.

## Remaining acceptance

- Confirm official subject names, codes and their office assignments with the
  department.
- Verify imports and exports with representative approved data; automated
  import tests exist, but production workbook acceptance is not recorded here.
- Review the effect of subject deactivation or office changes on officer-subject
  assignments, historical records and dependent AIP and estimate workflows.
  Complete integration tests as those workflows are developed.
- Complete production-environment and user acceptance checks.

Older Subject documentation may describe earlier stages or gate names; consult
current routes and tests when assessing implementation status.
