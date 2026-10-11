# Wireframes

Every screen of Abonemen 1.0, drawn with the Abonemen design system (Utrecht CSS components and the Abonemen theme) and sample data for one household. Light theme unless noted; the dark and high-contrast themes use the same layouts.

Start with the [screen map](Flows.png): it shows how the screens connect.

## Screen map

| Screen | Image | Other states |
| --- | --- | --- |
| Screen map and main flows | [Flows.png](Flows.png) | – |

## Daily use

| Screen | Image | Other states |
| --- | --- | --- |
| Overview (desktop) | [Main.png](Main.png) | [contrast](Main-contrast.png), [dark](Main-dark.png) |
| Overview (phone) | [OverviewMobile.png](OverviewMobile.png) | [dark](OverviewMobile-dark.png) |
| Subscriptions | [Subscriptions.png](Subscriptions.png) | – |
| Add or edit subscription | [AddSubscription.png](AddSubscription.png) | [errors](AddSubscription-errors.png) |
| Subscription detail | [SubscriptionDetail.png](SubscriptionDetail.png) | – |
| Overview, first visit (empty) | [OverviewEmpty.png](OverviewEmpty.png) | – |
| Subscriptions (phone) | [SubscriptionsMobile.png](SubscriptionsMobile.png) | – |
| Add subscription (phone) | [AddSubscriptionMobile.png](AddSubscriptionMobile.png) | – |
| Delete confirmation | [SubscriptionDelete.png](SubscriptionDelete.png) | [deleted](SubscriptionDelete-deleted.png) |
| Session timeout warning | [SessionTimeout.png](SessionTimeout.png) | [signed out](SessionTimeout-signed-out.png) |
| Reminder email | [ReminderEmail.png](ReminderEmail.png) | [payment](ReminderEmail-payment.png), [trial ends](ReminderEmail-trial-ends.png) |

## Calendar and statistics

| Screen | Image | Other states |
| --- | --- | --- |
| Calendar (month and list) | [Calendar.png](Calendar.png) | [list](Calendar-list.png) |
| Calendar (phone, list) | [CalendarMobile.png](CalendarMobile.png) | – |
| Statistics | [Statistics.png](Statistics.png) | [per year](Statistics-per-year.png) |

## Sign in and setup

| Screen | Image | Other states |
| --- | --- | --- |
| Sign in | [SignIn.png](SignIn.png) | [locked](SignIn-locked.png), [signed out](SignIn-signed-out.png), [wrong password](SignIn-wrong-password.png) |
| Second step: code | [TwoFactor.png](TwoFactor.png) | [error](TwoFactor-error.png), [recovery code](TwoFactor-recovery-code.png) |
| Forgot and reset password | [ForgotPassword.png](ForgotPassword.png) | [email sent](ForgotPassword-email-sent.png), [link expired](ForgotPassword-link-expired.png), [new password](ForgotPassword-new-password.png) |
| Accept an invite | [AcceptInvite.png](AcceptInvite.png) | [expired](AcceptInvite-expired.png) |
| Setup 1: check server | [SetupCheck.png](SetupCheck.png) | – |
| Setup 2: admin account | [SetupAdmin.png](SetupAdmin.png) | [password](SetupAdmin-password.png) |
| Setup 3: recovery key | [SetupRecoveryKey.png](SetupRecoveryKey.png) | – |
| Setup 4: household | [SetupHousehold.png](SetupHousehold.png) | – |
| Setup 5: done | [SetupDone.png](SetupDone.png) | – |

## Settings

| Screen | Image | Other states |
| --- | --- | --- |
| Settings: household | [Household.png](Household.png) | – |
| Settings: profile and preferences | [SettingsProfile.png](SettingsProfile.png) | – |
| Settings: security | [SettingsSecurity.png](SettingsSecurity.png) | – |
| Settings: reminders | [SettingsReminders.png](SettingsReminders.png) | [test sent](SettingsReminders-test-sent.png) |
| Settings: categories, payment methods, tags | [SettingsLists.png](SettingsLists.png) | – |
| Settings: connections | [SettingsConnections.png](SettingsConnections.png) | – |
| Settings: export and delete | [SettingsData.png](SettingsData.png) | – |
| Invite someone (dialog) | [InviteDialog.png](InviteDialog.png) | [sent](InviteDialog-sent.png) |
| Confirm it is you (before sensitive actions) | [ConfirmIdentity.png](ConfirmIdentity.png) | [delete household](ConfirmIdentity-delete-household.png), [export ready](ConfirmIdentity-export-ready.png) |

## Administration

| Screen | Image | Other states |
| --- | --- | --- |
| Admin: users and registration | [AdminUsers.png](AdminUsers.png) | – |
| Admin: email, sign-in, outbound | [AdminSettings.png](AdminSettings.png) | [smtp error](AdminSettings-smtp-error.png) |
| Admin: backups, keys, health | [AdminBackups.png](AdminBackups.png) | – |
| Recovery mode (lost master key) | [RecoveryMode.png](RecoveryMode.png) | [other users](RecoveryMode-other-users.png) |
| Error pages | [ErrorPage.png](ErrorPage.png) | [403](ErrorPage-403.png), [419](ErrorPage-419.png), [500](ErrorPage-500.png), [503](ErrorPage-503.png) |
| Security log (admin and own account) | [AdminAudit.png](AdminAudit.png) | – |

## Sample data

The screens share one household (Sanne, Joost and Mila) with 12 subscriptions costing € 309,82 a month. Totals, calendar, statistics and lists all add up to that figure, so the screens can be used as test fixtures.
