# Vote PDF export

Administrators can download the vote list as a PDF from the vote
management page.

The PDF contains vote codes, descriptions and statuses, including both
active and inactive votes. The download route requires authentication
and the manage-master-data permission.

## Tests

    php artisan test --filter=VotePdfExportTest
    php artisan test
    npx tsc --noEmit
    npm run build