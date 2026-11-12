# Estimate requirements and implementation status

## Purpose

An estimate requests expenses for holding a programme, such as a workshop.
The estimate format is based on the applicable circular guidelines.

## Agreed requirements

### Common format and funds

- All funds use the common estimate format.
- Applicable rates or limits and the closing paragraph can differ by fund.
- Signature persons can differ.
- Administrators must be able to update the common format and applicable
  rules when a new circular is issued.
- The exact rules and rates must be configured from the applicable circulars;
  rates in example estimates are not automatically treated as current.

### Individual estimates

- Users can add, edit and remove items for an individual estimate.
- Removing an item from one estimate must not remove it from the common
  format or from future estimates.
- The estimate total must be recalculated after item changes.
- A saved estimate should retain the format, rates, circular wording and
  other details applied to it, even if the common settings change later.

### Circular wording

- The estimate includes a circular rule paragraph above the signature area.
- The paragraph may refer to more than one circular.
- An administrator maintains the common default circular reference and
  rule paragraph.
- A user can edit the wording for an individual estimate.
- The exact approved wording will be supplied later. Example wording must
  not be represented as an official circular.

### Languages

- Sinhala is the default estimate language.
- Users should be able to translate an estimate from Sinhala into English
  or Tamil, then review and correct the translation.
- The original text and the corrected translation should be retained.
- Translation must preserve figures, dates, circular numbers, fund names
  and calculations.
- Sinhala, Tamil and English must display correctly in the estimate and
  its generated PDF.

## Implemented

- The `estimate_circular_settings` database table stores a circular number
  and rule paragraph.
- An authenticated user with the `manage-master-data` permission can view
  and update the current common settings.
- The settings page currently stores one current default using record ID 1.
- Database tests cover saving, updating and preserving multilingual text
  with line breaks.
- Access and management tests cover guests, regular users, administrator
  access and circular-number length validation.
- The settings page has been checked in the browser for saving and
  retaining entered text after refresh.

- `EstimateCalculationService` calculates line amounts and totals using
  integer cents. The current line calculation supports whole units and
  people; fractional quantities and other rules remain to be confirmed.

- The `estimates` and `estimate_items` tables can store an estimate title,
  its circular paragraph and separate expense rows. Programme and budget
  links, calculations in the estimate workflow, and the estimate page
  remain to be developed.

- `EstimateService` validates and saves a new estimate and its rows in a
  database transaction. It calculates and returns the total in integer
  cents; it does not yet store or display the total.

## Still to be developed

- Estimate creation, editing, calculations and output.
- Administration and versioning of the common estimate format.
- Fund-specific rates or limits, closing paragraphs and signature persons.
- A copy of the applied circular wording and other rules on each estimate.
- Automatic translation, user correction and retention of translations.
- Sinhala and Tamil rendering tests for generated estimate PDFs.
- Circular settings audit history and version history.

## Notes

- The circular settings page currently stores only the current common
  default. It does not yet keep historical versions.
- No translation service is connected yet.
- Expense categories are maintained separately. Their relationship to
  estimate items and any official category codes still needs to be defined.