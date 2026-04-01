# Advanced Content Reminder -- Expiration Submodule

## How to use

-   Once installed, navigate to `/admin/structure/types/manage/[content-type]/fields`
    everywhere you want this field to appear
-   Navigate to `/admin/config/content/adv-content-expiration` determine your
    Configured Interval and save.
-   Click the `Recalculate all nodes` button to update the expiration date
    field.

------------------------------------------------------------------------

## Overview

The `adv_content_reminder_expiration` submodule provides
policy-driven Advanced Content Reminder Expiration management for Drupal nodes.

This module:

-   Creates `field_expiration_date` field storage (date-only)

-   Does **not** automatically attach the field to content types

-   Allows administrators to enable the field per content type

-   Automatically calculates expiration based on:

    **Node Updated Date + Configurable Date Interval**

-   Provides a batch process to recalculate expiration dates for all
    nodes

------------------------------------------------------------------------

## Architecture

### Field Creation

On install, the module creates field storage for:

    field_expiration_date

The field must be manually added to content types at:

    /admin/structure/types/manage/[content-type]/fields

------------------------------------------------------------------------

## Expiration Policy

Expiration is calculated as:

    Expiration Date = Node Changed Date + Configured Interval

-   Uses the node's `changed` timestamp
-   Normalized to midnight
-   Interval is configurable (year, month, day)
-   Editors cannot override expiration manually

------------------------------------------------------------------------

## Configuration

Admin UI:

    /admin/config/content/adv-content-expiration

Configurable settings:

-   Interval value (integer)
-   Interval unit (year, month, day)
-   "Recalculate all nodes" batch button

------------------------------------------------------------------------

## Batch Recalculation

The batch process:

-   Finds all bundles where `field_expiration_date` exists
-   Processes nodes in chunks (50 per batch iteration)
-   Recalculates expiration using the same service logic as presave
-   Safe for thousands of nodes

------------------------------------------------------------------------

## Services

Service ID:

    adv_content_reminder_expiration.expiration_manager

Responsibilities:

-   Calculate expiration date
-   Recalculate single node
-   Retrieve node IDs eligible for recalculation

------------------------------------------------------------------------

## Hooks Implemented

-   `hook_install()` -- Creates field storage
-   `hook_entity_presave()` -- Enforces expiration policy

------------------------------------------------------------------------

## Dependencies

-   Drupal 10 / 11
-   Node module
-   adv_content_reminder

------------------------------------------------------------------------

## Deployment Notes

-   Config schema included
-   No automatic field attachment
-   Batch processing
-   Policy enforcement prevents manual expiration overrides

------------------------------------------------------------------------

## Maintainer Notes

-   Interval defaults to 1 year if not configured
-   Expiration logic centralized in ExpirationManager service
-   Batch and presave share identical calculation logic
