# Estimate references — initial implementation

A new estimate must select an AIP activity's office assignment in an open
financial year and active office. Its reference is generated on save as
`YEAR/OFFICE-CODE/EST/NNNN`. A locked year and per-office/year counter
serialize assignment; the counter, estimate and items commit together.
A unique reference index rejects collisions. Existing estimates retain a null
reference and remain viewable as legacy records. No old data is backfilled.
The saved reference is not regenerated when an office code changes. If a
reused office code conflicts with an existing reference, the save is rejected.

The existing estimate permissions remain unchanged (system administrator via
the existing gate). Subject-director workflows and office-scoped permissions
are not yet implemented. Reference correction, cancellation, approval and
their audit policies are not implemented. Do not delete saved estimates or
sequence records to reset numbering. Validate official requirements with the
department before production. Concurrent multi-connection behavior, MySQL
migration, automated tests and browser workflow still need verification.
