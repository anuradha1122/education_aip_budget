# Data design

## Offices

| Field | Purpose |
| --- | --- |
| id | Internal identifier |
| code | Unique office code |
| name | Office name |
| type | Provincial or zonal |
| is_active | Indicates whether the office is active |
| created_at | Creation time |
| updated_at | Last update time |

An office should be deactivated instead of deleted when it has
historical records.

Office type validation and historical-record protections are planned
and are not yet implemented.
