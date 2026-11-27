# M01 — Foundation acceptance record

## Development scope

The foundation work below is complete for the scope agreed with the user in
the development environment. Production deployment and acceptance remain open.
This record does not declare the whole application production-ready.

- Laravel uses UTC and the MySQL connection is configured for a UTC session.
  The 11 updated audit/history pages and the access-review PDF generation time
  display Sri Lanka time. Other timestamp displays, scheduled jobs, database
  connections and deployed environments require separate verification.
- Currency is Sri Lankan rupees; two decimal places such as `1200.50` are
  accepted. A system settings page for changing currency or timezone is not
  required. Currency formatting in other workflows and exports must be checked
  as those workflows are completed.
- AIP uses English. Sinhala, Tamil and English estimate support is part of the
  estimate module and remains to be developed and verified there.
- The AIP activity creation form selects the current Sri Lanka calendar year
  when it exists and is open. Other open years remain selectable; if the current
  year is missing or closed, the user selects an open year manually. This is
  a form default, not a global year setting.
- Initial estimate references use the year, office code, EST and an office/year
  sequence. An initial isolated MySQL probe produced two distinct sequential
  references while concurrent workers were active. Broader concurrency, other
  record types, correction history and approval rules remain for their relevant
  workflows and production acceptance.
- Settings Excel and configuration PDF are not required for the agreed fixed
  settings. Security and operational alerts will be considered in the operations
  module. Reference-number correction and estimate editing are estimate-module
  work and are not claimed complete here.

## Evidence reported by the developer

- Full suite: 616 PHP tests passed, 4,309 assertions.
- Sri Lanka timestamp formatter: two Node tests passed.
- Prior targeted checks, build and browser tests passed for the timezone display,
  AIP default year, and initial estimate references. The initial MySQL reference
  probe produced distinct sequential references in a disposable test database.

## Deployment and acceptance checks

Verify the configured MySQL session timezone and Laravel clock in each deployed
environment, including workers and other database connections. Verify displayed
and stored timestamps with new test records; old development records may have
been stored under the previous session timezone and must not be blindly shifted.
Confirm scope and official reference requirements with the department and run
end-to-end acceptance checks before production.
