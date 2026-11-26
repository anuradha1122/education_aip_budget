# M03: Users, roles and access — implementation status

**Status: in progress.** Core user management and several role permissions are
implemented; access to future modules and final acceptance remain open. A full
suite of 605 tests passed before the one-account-per-officer change; targeted
regression and browser checks passed after that change.

## Current access model

- Public registration is disabled. The existing `is_admin` flag grants broad
  master-data access and access to all offices in the AIP register. Restrict
  this flag to trusted provincial system administrators.
- User role and office assignments are stored separately with one current
  assignment per user. Roles recorded are `provincial_admin`, `zonal_admin`
  and `subject_director`. An active office is required for the last two.
- A province-wide `provincial_admin` can manage eligible non-system users,
  financial years, organisational master data and AIP activities and assignments.
  AIP import requires a separate grant from a system administrator. The
  subject-director role has the limited AIP register scope described below.
- A recorded `zonal_admin` at an active office can view a read-only AIP
  register filtered to that office. Other offices' assignments and legacy
  activity-level subject/officer details are excluded from that response.
  Zonal administrators can manage subject-director accounts in their own
  active office. They cannot access financial-year or master-data management,
  and cannot grant AIP import permission.

## User management

- Existing administrators can create accounts with a confirmed password of at
  least 12 characters and a recorded role and office. Passwords are hashed.
  Account creation and its assignment audit are transactional.
- Administrators can change a non-admin user's role and office. Changes are
  audited with actor and previous/new values. An unchanged assignment does not
  create another event. A zonal office change affects the next register request.
- Administrators can edit a non-admin user's name and email and optionally
  reset their password. Changing email or password updates the access version
  and rotates the remember token. Audit values contain names and emails and a
  password-changed flag, not passwords or hashes. Account-detail editing passed
  feature tests and a browser check.
- Administrators can deactivate and reactivate non-admin accounts. The account
  and previous records remain; changes are audited. Inactive users cannot log
  in. Their existing sessions are rejected on the next request. A session from
  before deactivation remains invalid after reactivation; a new login works.
  Automated checks and a browser deactivation/reactivation check passed.
- Changes to existing `is_admin` accounts are not available on these forms.
  The signed-in user has separate profile and password settings. Review these
  settings and all other account-recovery paths before production.

## UI conventions

The Users page opens on the user list and provides labeled action buttons with
icons for creation and editing. Forms use two columns on wider screens and one
column on smaller screens. Apply consistent list-first layout, action labels,
icons, validation messages and responsive behavior as each module is completed.

## Evidence reported by developer

- `UserDetailsManagementTest`: 3 passed, 30 assertions.
- `UserAccountStatusTest`: 4 passed, 35 assertions.
- `UserManagementTest`: 4 passed, 33 assertions.
- `UserAccessManagementTest`: 3 passed, 30 assertions.
- `UserAccessAssignmentTest`: 3 passed, 17 assertions in the earlier run.
- `AipRegisterOfficeAccessTest`: 2 passed, 65 assertions.
- TypeScript and Vite build passed after the latest account-detail change.
- Browser checks were reported successful for user creation, editing recorded
  access, zonal register access, and account deactivation/reactivation.

These checks are evidence for the versions tested; they are not a complete
security review, full-suite run or production acceptance.

## Remaining work

- Extend subject-director personal scope beyond the AIP register as each
  relevant module is implemented; deny access by default.
- Review provincial and system administrator boundaries and any future
  administrator transition against the department’s requirements.
- Continue reviewing permission revocation, account lifecycle and recovery
  behavior as further modules are implemented.
- Verify office and personal scope across every relevant action, API, report,
  export, attachment and background task as those features are implemented.
- Test remember-me, simultaneous access changes and status changes, production
  session configuration, rate limits, recovery flows and audit retention.
- Specify delegation and monetary approval limits with the department rather
  than assuming the workbook's draft rules.
- Verify access review coverage for future permissions, and implement
  notifications and email where required; complete automated and browser UAT.

## Project plan and dependencies

M01 foundation and configuration, M02 master data and M04 financial years are
also in progress. M05 AIP uses M02/M03/M04 and must be integrated with final
permissions and period locks. The wider plan includes allocations, programmes,
estimates, approvals, actual expenditure, reports, audit and operations.
Estimates are records and do not reduce allocations; final actual expenditure
reduces the appropriate office allocation. The accounts branch handles payment.
No module is declared complete or ready for launch by this document.

## User history page

Existing administrators can open a user's paginated assignment and account
history. Only the selected user's events and approved detail fields are
returned. Passwords and hashes are excluded. Automated tests and an administrator browser check passed. The page does not
reconstruct events that were never recorded.

The user history automated tests, TypeScript check, build and an administrator
browser check passed in the development environment. Office IDs are now
resolved to codes and names for display without changing audit values; this
follow-up passed automated and browser verification. Office names shown
are current master-data values, not historical name snapshots.

## Subject-director identity link

Administrators can link a subject-director user to an active officer in the
same active office; changes are recorded in user history. The link supports limited subject-director AIP register access when the
recorded role and office also match. Automated tests and browser verification passed.
The link can become stale if a user changes role or office, or an officer is
transferred or deactivated. Every future personal-access decision must recheck
role, office and officer status against current data and deny on mismatch.
Removal and officer transfer guards are described below. Existing stale links
still require review before production.

### One account per officer

Each subject-director account can link to one officer, and an officer can be
linked to at most one user account. This rule also applies when the linked
account is inactive. To link that officer to another account, an authorised
administrator must first remove the existing link. The database enforces the
limit, and the Users page marks officers already linked to another account.
The migration checks for duplicate links and stops without changing them if any
are found.

The officer–subject assignment is separate from the user–officer link: an
officer may be assigned to multiple subjects in the same office. Multiple
subject assignments do not by themselves grant access to additional AIP
activities; the register remains scoped to the officer’s matching office-level
activity links described below.

The migration, officer-link and multiple-subject tests, TypeScript check,
production build and browser check passed in the development environment.

## Subject-director AIP register

A subject director with a linked active officer in the same active office may
view only activities whose office-specific link names that officer. The response
contains only that matching office link; legacy activity-level assignments are
hidden. Office and officer status and office membership are rechecked on each
request. An unlinked, inactive or transferred officer is denied register access.
This does not provide estimate creation, approval, exports or personal access
to other modules. Automated and browser checks passed for the register scope; wider acceptance
remains open.

## User search

The administrator user list supports server-side name or email search, with
search retained during pagination. Search access remains protected by the
existing administrator gate. Automated and browser checks passed.

The subject-director AIP scope and officer-link tests passed, and the developer
reported a successful browser check. Subject-director access to estimates and
other modules is still outstanding.

## Removing an officer link

Administrators can remove a non-admin user's officer link. Changing that user's
role or office also removes any link, so changing back cannot silently restore
personal access. Both operations create an officer-unlinked audit event when a
link exists. Automated tests and a browser check passed for these changes. Inactive or
transferred officers remain denied at request time; their later reactivation or
return to the same office still requires lifecycle-policy review.

User search tests and a browser search check passed in development.

## Officer transfer and status guard

When an officer is linked to any user account, `OfficerService` rejects a
change to the officer's office or active status. The administrator must first
remove every user link from the Users page, which records an unlink event. This
prevents a link becoming effective again when an officer returns or is
reactivated. Name and designation changes are not blocked by this guard. Direct
database writes bypass this service. Automated tests and a browser check passed; review existing stale links and the officer-subject assignment transfer workflow
before production.

The developer reported successful officer unlink tests, frontend build and a
browser check. The role and office assignment changes remove existing links.

## Self-service password changes and resets

A password change through account settings increments the account access
version and rotates its remember token. The current session receives the new
version; other sessions are rejected on their next request. A token-based
password reset also increments the version and rotates the remember token. Both
paths record an audit event without password or hash. The reported automated
tests and password-change browser check passed. Review email re-authentication and password policy before production.

## Self-service profile changes

A self-service name or email update records previous and new values and the
user who made the change. When the email changes, the account access version
and remember token also change; the session making the change stays active,
while older sessions are rejected on their next request. Unchanged profiles
produce no audit event. The reported automated tests and browser check passed. Email re-authentication policy remains to be reviewed before launch.

The developer reported that the self-service password change and reset tests,
account-status regression tests and password-change browser check passed.

## Account removal policy

Self-service account deletion is disabled: the profile page does not offer it,
and the DELETE profile route and controller action have been removed. Existing
administrators deactivate and reactivate eligible non-admin accounts using
the audited user-management workflow. Deactivation retains users and their
history and rejects their existing sessions on their next request. Changes to
existing administrator accounts require a separate management policy.

The profile, profile-session audit and account-status feature tests passed,
as did the TypeScript check, production build and browser check reported on
20 November 2026. Permanent deletion or anonymization is not implemented; establish
retention and exceptional-removal procedures before production.

## Next verification step

Review the current route and permission inventory against the M03 plan,
including direct URLs, exports and reports. Access review and exports exist;
delegated monetary approval limits and access to future modules remain open.
Complete the necessary integration and acceptance checks before marking M03
complete.

## Access review and exports

The access review shows account status, role, office, linked officer, flags and
selected current permissions. Search supports name, email, office name and
office code. Excel and PDF exports apply the same search across all matching
pages and exclude credentials. The report does not cover every system
permission. Zonal administrators see only subject-director accounts in their
own active office; provincial and system administrators have broader scope as
described under delegated user management.

Access-review, office-search, export, PDF and director-consistency tests passed,
and the developer reported successful browser and download checks. Larger
report layout and performance still require acceptance testing.

## Email change confirmation

Self-service email changes require the account's current password, checked
against the locked user record before saving. A wrong or missing password
blocks the entire update. Name-only changes do not require a password. Existing
email-change audit and other-session revocation still apply. Automated tests and browser verification passed. Review production rate-limit configuration.

The developer reported the director access-review consistency and subject
director access tests passed after the prior update.

## Email confirmation attempt limit

Five incorrect or missing current-password submissions for an email change
limit further email-change attempts for that account for 60 seconds. Name-only
updates remain available. Attempts are counted after a rejected transaction;
a successful email change clears the counter. Automated and browser checks passed. Production must use a shared, working cache for rate limits;
verify concurrency and cache behavior on the deployment infrastructure.

The developer reported that email confirmation, profile audit, profile update,
account-status tests, the TypeScript check, build and browser check passed.

## AIP import permission administration

System administrators can grant or revoke AIP import permission for eligible
system administrators and province-wide provincial administrators. Import is
denied by default and requires a separate grant. Changes are audited. A role
change that makes a provincial administrator ineligible revokes the grant;
changing back does not automatically restore it. The provincial import feature
and related regression tests, build and browser check passed.

## Provincial AIP access

A provincial administrator with a valid province-wide assignment can view the
AIP register across offices, create activities and manage office and funding
assignments. Closed-year validation and audit rules apply. AIP import remains
separately controlled. The relevant access, creation and assignment tests and
browser checks passed.

## Financial-year access

System administrators and provincial administrators can view, create, edit,
close and reopen financial years and view their audit history. A provincial
administrator must have a province-wide assignment with no office.

Zonal administrators cannot access financial-year routes. Reopening still
requires a reason and records the actor and change in the audit history.
Financial-year access is controlled separately from user-management and
master-data permissions; qualifying provincial administrators also have those
permissions under the separate rules documented below.

## Provincial organisational master-data access

System administrators and provincial administrators with a province-wide role
can access organisation and master-data routes, including officer-subject
assignments, audit, imports and exports. Zonal and subject-director users are
not granted this access. User management, estimate routes and AIP import
retain their existing restrictions. The permission is checked server-side and
shared separately for sidebar navigation. Automated and browser checks passed in the development environment.

## Provincial AIP office and funding access

System administrators and province-wide provincial administrators can link
activities to offices and associate votes and funds. Closed-year validation
and audit events apply. Zonal administrators and subject directors cannot
use these management routes. AIP import remains separately restricted.

Automated tests and browser verification passed.

## Access review permission coverage

The access review page, Excel export and PDF show current financial-year,
organisational-data and AIP-assignment permissions alongside AIP register
and import access. Inactive accounts are shown as not allowed. The report
does not cover every system permission.

The delegated user-management tests passed: 4 tests, 75 assertions.
The full suite passed: 605 tests, 4,206 assertions. The TypeScript check,
build and browser checks also passed.

## Password change policy

Passwords assigned when an account is created or reset by an administrator
are not temporary. Users are not required to change them at first login or
after an administrator reset. Users may change their password through
account settings or use the password reset process when needed.

## Role changes in existing sessions

A feature test confirms that changing a signed-in user from provincial
administrator to zonal administrator removes access to financial-year,
master-data and AIP-assignment routes on the next request. The user remains
signed in and can access the zonal AIP register. The change is audited.

RoleChangeSessionAccessTest passed: 1 test, 15 assertions.

## Password-reset link revocation

A successful administrator password change or self-service password change
invalidates an outstanding reset link. A rejected password change leaves the
link valid. The reset controller rechecks the token after locking the user.

PasswordResetLinkRevocationTest passed: 3 tests, 13 assertions. Password-update,
password-reset, account-detail and session-revocation regression tests passed.
Concurrent reset behavior still requires separate verification.

## Delegated user management

This section supersedes earlier descriptions that restrict user management
to existing system administrators.

System administrators and province-wide provincial administrators can manage
non-system user accounts across offices. Zonal administrators can manage only
subject-director accounts assigned to their own active office. The user list,
history, access review and Excel/PDF exports are scoped to those accounts for
zonal administrators. Zonal administrators cannot assign another office or a
higher role, manage other zonal administrators, or grant AIP import permission.
Existing system administrator accounts remain protected by the current account
management restrictions; only system administrators can grant AIP import.
The delegated user-management tests and browser checks passed. After the
one-account-per-officer change, delegated management passed 4 tests with 75
assertions, and officer unlink passed 2 tests with 17 assertions.
