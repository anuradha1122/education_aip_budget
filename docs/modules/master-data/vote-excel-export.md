# Vote Excel export

Administrators can download the complete vote list as an Excel workbook
from the vote management page.

The workbook contains vote code, description and status, including both
active and inactive votes. The download route requires authentication
and the manage-master-data permission.

Vote codes and descriptions are written as text in the workbook.

## Tests

    php artisan test --filter=VoteExportTest
    php artisan test
    npx tsc --noEmit
    npm run build