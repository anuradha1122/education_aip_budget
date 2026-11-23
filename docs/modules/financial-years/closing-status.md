# Financial-year closing — in progress

Existing administrators can mark a financial year closed from Financial years.
The action locks the year row, updates its status and records an audit event in
one transaction. Closing an already closed year creates no new event. Guest and
regular-user requests must be denied. Existing activity creation and financial-
year editing checks reject closed years; these paths still require regression
verification in the deployment environment.

This is not the completed M04 period-lock workflow. Reopening, reason and
authority rules, validation across all other financial operations, concurrent
write checks on the production database and acceptance testing remain open.
There is no reopening action in this change. Do not close a production year
until the department agrees the workflow and all relevant operations have been
verified. Automated and browser verification of this change are pending.
