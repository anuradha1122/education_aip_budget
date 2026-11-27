# Funds — development review

## Implemented

The Funds module supports listing and searching by code or name, creation,
editing, unique normalised codes, and activation or deactivation without
deleting the fund. Changes are recorded in an audit history. The module also
provides an Excel import template, validation and reviewed import, Excel export
and PDF export. The import process checks for duplicate codes and rejects the
import if a conflict occurs after review.

The organisational-data gate permits system administrators and eligible
province-wide provincial administrators to manage funds. Zonal administrators
are not granted Fund management. Access is checked on the server.

## Evidence reported by the developer

- The full application suite passed 616 tests and 4,309 assertions before this
  documentation-only review. The suite included Fund and provincial
  master-data access tests.
- The developer reported successful browser checks for test-fund creation,
  search, editing, duplicate-code rejection, deactivation, audit history,
  Excel and PDF downloads, and the requested role boundaries.

## Remaining acceptance

- Confirm official fund codes and names with the department.
- Test the import/export process with representative approved fund data;
  automated import tests exist, but this review does not claim acceptance of
  a production workbook.
- Verify the effect of fund deactivation on historical records and on dependent
  funding and expenditure workflows as those workflows are completed.
- Complete production-environment and user acceptance checks.

Older Fund documentation may describe earlier stages and older gate names;
consult current routes and tests when assessing implementation status.
