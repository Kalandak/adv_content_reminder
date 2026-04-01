# Advanced Content Reminder

## Overview

Content on Drupal sites often becomes outdated or requires periodic review, but there is no built-in way to proactively
notify content owners. **Advanced Content Reminder** automates this process by sending configurable reminders to ensure content stays accurate, relevant, and maintained.

### Example use case

An organization publishes hundreds or thousands of pages across departments. Each page owner is responsible for keeping their content up to date, but manual tracking is inconsistent.

With this module:

-   A reminder can be configured to notify authors every 6 months
-   Emails are sent to the original author or designated users
-   Editors receive prompts to review and update stale content

This ensures content governance without requiring manual follow-up.

It allows administrators to:

-   Select which content types should be monitored
-   Configure reminder email templates
-   Automatically send reminders at defined expiration intervals
-   Send test reminder emails
-   Process reminders via Drupal Queue API (cron-driven, scalable)

The module is compatible with:

-   SMTP module
-   Mail System module
-   Symfony Mailer (including Symfony Mailer Lite)

------------------------------------------------------------------------

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

------------------------------------------------------------------------

## Configuration

### Content Type Monitoring

Path: `/admin/config/content/adv-content-reminder`

Administrators can select which content types should participate in
expiration monitoring.

### Configurable Email Templates

Path: `/admin/config/content/adv-content-reminder/emails`

Templates fields:

-   Days relative to expiration (+ or -)
-   Email Subject
-   Email Body

Templates support Drupal token replacement using: - Node - Content
Author (User)

------------------------------------------------------------------------

### Queue-Based Processing

Queue ID: `adv_content_reminder_queue`

Flow:
1. Cron identifies qualifying nodes
2. Queue item created
3. Queue worker processes email
4. Logs success or failure

------------------------------------------------------------------------

### Test Email Form

Path: `/admin/config/content/adv-content-reminder/test-email`

Allows sending any configured reminder template to a specified email
address.

------------------------------------------------------------------------

## Required Field

Nodes must contain:

`field_expiration_date`

Type: Date (Y-m-d)

------------------------------------------------------------------------

## Email Delivery

Emails are sent via:

`plugin.manager.mail`

Formatted through:

`adv_content_reminder_mail()`

Works with SMTP / Mail System / Symfony Mailer handle transport.

------------------------------------------------------------------------

## Logging

Log channel: `adv_content_reminder`

View logs: `/admin/reports/dblog`

Logs include:
- Queue creation
- Successful sends
- Failures
- Missing template warnings

------------------------------------------------------------------------

## Service Definition

Service ID: `adv_content_reminder.manager`

Primary class:
`Drupal\adv_content_reminder\Service\ReminderManager`

------------------------------------------------------------------------

## Configuration Storage

Config object: `adv_content_reminder.settings`

Stores:
- monitored_content_types
- email_templates

------------------------------------------------------------------------

## Maintenance Commands

Run queue manually:

    drush queue:run adv_content_reminder_queue

------------------------------------------------------------------------

## Maintainers

Current maintainers:

- [Kalanda Kambeya (kkambeya)](https://www.drupal.org/u/kkambeya)
