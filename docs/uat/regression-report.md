# GoPasig Regression Report

Date: 2026-07-29
Scope: Phase C UI/UX standardization, synchronization, and safety regression.

| Module | Regression Scope | Result | Notes |
| --- | --- | --- | --- |
| Admin Dashboard | Loading, navigation, dashboard data, no native dialogs | Pending manual UAT | Automated build/checks recorded separately |
| Bus Management | Save/delete duplicate prevention, table refresh, polling sync | Pending manual UAT | Shared unsafe fetch guard applies to POST/PUT/PATCH/DELETE |
| Driver Management | Suspend/delete/save feedback and duplicate prevention | Pending manual UAT | Native confirm migrated to shared modal |
| Dispatch | Manual and scheduled dispatch workflows | Pending manual UAT | Dispatch pipeline unchanged |
| Schedules | Scheduled dispatch and route assignments | Pending manual UAT | No backend changes |
| Service Alerts | Broadcast, resolve, archive, history, counts | Pending manual UAT | Native dialogs migrated to shared modal/toast |
| Maintenance | Schedule, cancel, complete, delete, bus runtime sync | Pending manual UAT | Shared duplicate guard applies |
| Incidents | Report, resolve, delete, fleet visibility | Pending manual UAT | No persistence changes |
| Reports | Export and report-builder UI | Pending manual UAT | Native dialogs migrated where detected |
| Fleet Monitoring | Overview, performance, search, maintenance | Pending manual UAT | Search debounce confirmed |
| Driver Portal | Login, start trip, end trip, GPS, incident report | Pending manual UAT | Native alerts migrated where detected |
| Commuter Portal | Routes, schedule, ETA, fleet status, alerts, route health | Pending manual UAT | Native alerts migrated where detected |
