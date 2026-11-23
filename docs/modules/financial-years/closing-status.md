# Financial-year closing — in progress

Existing administrators can mark a financial year closed from Financial years.
The action locks the year row, updates its status and records an audit event in
one transaction. Closing an already closed year creates no new event. Guest and
regular-user requests must be denied. Existing activity creation and financial-
year editing checks reject closed years; these paths still require regression
verification in the deployment environment.

This is not the completed M04 period-lock workflow. A closed year can be reopened by an existing administrator with a required
reason; the actor, reason and old/new values are recorded in the audit history.
The department must confirm reopening authority and approval rules. Validation
across all financial operations, concurrent write checks on the production
database and acceptance testing remain open. Do not close a production year
until the department agrees the workflow and all relevant operations have been
verified. The developer reported that close, closed-year edit and provincial AIP creation
tests, TypeScript, build and a closed-year browser check passed. Reopening
tests and browser verification are pending.
## Financial-year access

System administrators and provincial administrators can view, create, edit,
close and reopen financial years and view their audit history. A provincial
administrator must have a province-wide assignment with no office.

Zonal administrators cannot access financial-year routes. Reopening still
requires a reason and records the actor and change in the audit history.
Financial-year access does not grant user-management or master-data access.