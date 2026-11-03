# Office import

Administrators can import offices from an XLSX workbook.

## Process

1. Download the office import template.
2. Enter office details and save the workbook.
3. Upload the workbook and select **Validate file**.
4. Review the validation results.
5. When all rows are valid, select **Import offices**.

Validation alone does not save offices. Import requires the same workbook
that was successfully validated in the current session. If the selected
file changes, validate it again before importing.

## Checks

- Import is restricted to users allowed to manage master data.
- The workbook must pass the import validation rules.
- Office codes are checked again when importing.
- If a code becomes a duplicate after preview, the import does not save
  any of the workbook rows.
- Imported offices produce office audit events.

## Testing

Run:

    php artisan test --filter=OfficeImportSaveTest
    php artisan test
