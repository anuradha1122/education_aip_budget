# M03: Users, roles and access — implementation status

**Status: in progress.** The recorded roles do not yet implement every
permission required by the project. This document reflects the checks shared
through 20 November 2026; final acceptance and the full test suite remain open.

## Current access model

- Public registration is disabled. The existing `is_admin` flag grants broad
  master-data access and access to all offices in the AIP register. Restrict
  this flag to trusted provincial system administrators.
- User role and office assignments are stored separately with one current
  assignment per user. Roles recorded are `provincial_admin`, `zonal_admin`
  and `subject_director`. An active office is required for the last two.
- The recorded `provincial_admin` role alone does not yet grant provincial
  administrator permissions. The subject-director role has a limited AIP
  register permission described below; the role is not fully implemented.
- A recorded `zonal_admin` at an active office can view a read-only AIP
  register filtered to that office. Other offices' assignments and legacy
  activity-level subject/officer details are excluded from that response.
  Import, user management and master-data routes retain their existing gates.

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
- Define and implement provincial administrator permissions independently from
  the legacy `is_admin` flag, with a safe administrator transition.
- Complete permission revocation, account lifecycle, password
  reset and first-login policy, including email re-authentication.
- Verify office and personal scope across every relevant action, API, report,
  export, attachment and background task as those features are implemented.
- Test remember-me, simultaneous access changes and status changes, production
  session configuration, rate limits, recovery flows and audit retention.
- Specify delegation and monetary approval limits with the department rather
  than assuming the workbook's draft rules.
- Complete the planned access review report, exports, notifications and email
  if applicable; update documentation and run full automated and browser UAT.

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
Removal and officer transfer guards are described below. Review the policy for
one-officer-to-multiple-user links and existing stale links before production.

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

Run the complete automated test suite against the current integrated codebase,
then investigate failures before marking M03 complete. Review the resulting
route and permission inventory against the M03 plan, including direct URLs,
exports and reports. User/role export and access review reporting, delegated
approval limits and the provincial administrator transition remain open.

## Access review page

Administrators can review current account status, recorded role, office and
linked officer, the existing administrator and import flags, and AIP register
and import gate results. Inactive accounts show AIP access as not allowed.
Role assignments alone do not grant all planned permissions; this page does not
represent a complete permission matrix. It is read-only, searches names and
emails, and paginates. It requires the existing administrator gate. Excel and
PDF access review outputs and additional permission coverage remain open.
Automated tests and browser checks for this page are pending.

The developer reported a full-suite run of 561 tests and 3,462 assertions
passing before the access review page was added.

## Access review Excel export

The administrator-only access review page offers an Excel download. It uses
the currently applied name/email search and includes matching users across all
pages. It reports account status, recorded assignments, selected flags and
current AIP register/import gate results. All cells are written as text.
Passwords, hashes and remember tokens are excluded. This is not a complete
permission report; PDF and broader permission coverage remain open. Feature
tests and a browser download check are pending.

The developer reported that the access review feature and related access
tests, TypeScript check, build and browser check passed.

## Access review PDF

The administrator-only access review PDF download uses the applied name or
email search and covers all matching pages. The landscape report shows account
status, recorded assignments, administrator and import flags, and selected AIP
register and import gate results. It excludes credentials and states that it
is not a complete permission matrix. The Excel export passed its feature tests,
TypeScript check, build and browser check. PDF automated and browser checks are
pending; larger report layout and performance require acceptance testing.

## Access review office search

The access review searches user name, email, office name and office code. The
Excel and PDF downloads use the same applied search for all matching pages.
Automated and browser verification for office searching is pending.

## Access review scope consistency

The developer reported that office-name and office-code search passed the
feature test and browser check. A new regression test checks that subject
director register access changes in the review when an officer or office is
inactive and checks the inactive-office result in Excel and PDF data. This new
test is pending verification in the development environment.

## Email change confirmation

Self-service email changes require the account's current password, checked
against the locked user record before saving. A wrong or missing password
blocks the entire update. Name-only changes do not require a password. Existing
email-change audit and other-session revocation still apply. Automated tests and
browser verification are pending. Review rate limits before production.

The developer reported the director access-review consistency and subject
director access tests passed after the prior update.

## Email confirmation attempt limit

Five incorrect or missing current-password submissions for an email change
limit further email-change attempts for that account for 60 seconds. Name-only
updates remain available. Attempts are counted after a rejected transaction;
a successful email change clears the counter. Automated and browser checks
are pending. Production must use a shared, working cache for rate limits;
verify concurrency and cache behavior on the deployment infrastructure.

The developer reported that email confirmation, profile audit, profile update,
account-status tests, the TypeScript check, build and browser check passed.

## AIP import permission administration

Existing administrators can grant or revoke the AIP import flag for existing
administrator accounts. An administrator cannot grant the flag to themselves;
another administrator must grant it. The flag cannot be granted to an
inactive account or a non-administrator. Changes are recorded with actor and
old/new values in user history; an unchanged flag creates no audit event.
Import routes retain their existing gates and check access on every request.
Automated and browser checks are pending. The existing administrator flag still
grants broad master-data access; a separate provincial administrator role and
more granular management permissions remain open.

The developer reported that email-change attempt limiting and profile
regression tests and a browser check passed. Production cache and concurrency
verification remain open.

## Provincial AIP register access

A user recorded as provincial administrator with no office assignment may
view the AIP register across offices. This is read-only and excludes legacy
activity-level subject and officer details for non-system administrators.
Import, activity creation and user-management permissions remain controlled
by their existing gates; the recorded provincial role does not grant them.
Removal of the role denies province-wide register access on the next request.
Automated and browser verification are pending; wider provincial administrator
permissions still require definition and implementation.

## Provincial AIP activity creation

The requirements draft assigns AIP record creation to the Provincial Budget
Administrator. A user with a valid province-wide provincial-admin assignment
can create an AIP activity using the existing validation, financial-year lock
and audit service. The dedicated route permission does not grant master-data,
user-management, audit-history or AIP-import access. Office assignment and
funding remain separate workflows. Automated and browser checks are pending.
The developer previously reported the provincial register and associated
regression tests and browser check passed.
