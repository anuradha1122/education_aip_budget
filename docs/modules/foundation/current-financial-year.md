# Default financial year for AIP activity creation

For users permitted to create an AIP activity, the form defaults to the
current calendar year in Asia/Colombo if that financial year exists and is
open. Other open years remain selectable. If the current year is missing or
closed, no year is selected; the user must choose an open year. The existing
server-side validation still rejects closed years.

This default is limited to the AIP activity creation form. It does not close
or reopen a year and does not define a global financial-year setting.
Automated tests and browser verification are required after installation.
