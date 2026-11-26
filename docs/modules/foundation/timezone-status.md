# Timestamp handling — in progress

The application timezone is UTC. The MySQL connection is configured with a
UTC session (`+00:00`) so new application-written TIMESTAMP values are
interpreted consistently. The audit pages format timestamp values using
Asia/Colombo and label them as Sri Lanka time. The access-review PDF shows
its generation time in the same timezone. Date-only values are unchanged.

Existing test records created while the MySQL session used Sri Lanka time may
represent instants 5 hours 30 minutes earlier than intended. No data has been
changed. Do not apply a blanket adjustment without establishing provenance.
Configuration must be checked separately for every database connection,
background worker and environment. Production acceptance and broader date-time
inventory remain open. Run the time-format tests and PDF test on the target
installation; verify new audit entries and the downloaded PDF in the browser.
