# GoPasig Manual UAT Report

Date: 2026-07-29
Tester: ____________________
Environment: Local UAT

| Test ID | Module | Objective | Steps | Expected Result | Actual Result | PASS/FAIL | Tester | Date |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| UAT-ADM-001 | Admin Dashboard | Verify dashboard loads current operational data | Login as Admin, open Dashboard | Dashboard cards, feeds, and maps load without console/API errors | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-ADM-002 | Bus Management | Verify save/delete actions show loading and prevent duplicate requests | Open Bus Management, perform save/delete once, then rapid-click | One unsafe request is accepted, duplicates are blocked, UI recovers | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-ADM-003 | Manual Dispatch | Verify dispatch assignment lifecycle | Select route, direction, bus, driver, dispatch | Dispatch succeeds only for eligible combinations | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-ADM-004 | Service Alerts | Verify broadcast, resolve, archive, and history refresh | Broadcast alert, resolve, archive, open History Vault | Status transitions and counts update correctly | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-ADM-005 | Maintenance | Verify start/complete/cancel/delete maintenance workflows | Execute each maintenance action | Bus runtime state and maintenance tables stay synchronized | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-DRV-001 | Driver Portal | Verify Start Trip | Login as Driver, start assigned trip | Trip starts once, button shows loading/does not duplicate | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-DRV-002 | Driver Portal | Verify End Trip | End an ongoing trip | Trip completes once and route/bus state updates | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-DRV-003 | Driver Portal | Verify incident reporting | Submit breakdown/accident report | Incident is stored and visible to staff modules | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-FLT-001 | Fleet Monitoring | Verify fleet dashboard refresh | Open fleet dashboard and switch sections | Data loads with no duplicate unsafe requests | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-FLT-002 | Fleet Maintenance | Verify maintenance completion | Complete maintenance record | Record completes and UI shows standardized feedback | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-COM-001 | Commuter Routes | Verify route browsing | Open commuter routes and view route details | Route data renders with no native browser dialogs | Pending manual execution | Pending |  | 2026-07-29 |
| UAT-COM-002 | Commuter Alerts | Verify service alert visibility | Open commuter alerts after admin broadcast | Active public alerts appear | Pending manual execution | Pending |  | 2026-07-29 |
