# GoPasig View Structure Skill Guide

This file defines the standard Blade foldering/layout pattern for the GoPasig dashboards.

## 1) Core Principles

- Keep layouts as shells only (doctype, assets, global frame).
- Put reusable UI in `resources/views/components/`.
- Put page-specific UI blocks in feature folders and include them from page entry files.
- Keep JS initialization per page in a dedicated include block.
- Avoid one giant Blade file for an entire module.

## 2) Standard Foldering

- `resources/views/layouts/`
  - App shells only (`admin.blade.php`, `fleet.blade.php`, etc.)
- `resources/views/components/`
  - Shared/reusable parts (`admin.header`, `admin.sidebar`, `fleet.topbar`, `fleet.sidebar`)
- `resources/views/admin/`
  - Entry pages and section includes for admin module
- `resources/views/fleet/`
  - Entry pages and section includes for fleet module

Recommended per feature:

- `resources/views/<module>/<page>/index.blade.php` (entry)
- `resources/views/<module>/<page>/_*.blade.php` (sections/partials)

## 3) Entry File Pattern

Use entry files as orchestrators only:

- `@extends(...)`
- `@section('title')`, `@section('breadcrumb')`
- `@include(...)` section blocks
- `@section('scripts')` include JS block

## 4) Layout Pattern

- Layout must not contain page-specific cards/tables/charts.
- Layout should only render:
  - sidebar component
  - topbar/header component
  - `@yield('content')`

## 5) Naming Rules

- `index.blade.php` = page entry
- `_header.blade.php`, `_kpi-strip.blade.php`, `_table.blade.php`, `_scripts.blade.php` = section partials
- Keep names explicit and feature-oriented.

## 6) Migration Checklist (Old -> Clean)

1. Create section partial files from monolithic page.
2. Replace monolithic markup with `@include(...)` calls.
3. Move page JS into `_scripts.blade.php`.
4. Keep existing IDs and selectors to avoid JS breakage.
5. Verify no visual regression and no missing routes/components.

## 7) Current Applied Baseline

- Fleet layout now uses reusable components:
  - `<x-fleet.sidebar />`
  - `<x-fleet.topbar />`
- Fleet dashboard page is being aligned to include-based structure (same style as admin dashboard entry).

