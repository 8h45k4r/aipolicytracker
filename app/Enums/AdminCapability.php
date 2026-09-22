<?php

namespace App\Enums;

/**
 * One named thing an administrator may do. Capabilities exist rather than bare role checks so
 * a route says what it needs, not who it trusts: adding a page means naming its capability,
 * not editing four role conditions.
 *
 * Each value is the name of a Gate ability, registered in AppServiceProvider.
 */
enum AdminCapability: string
{
    // Owner only. These either change how the platform runs or change who can change it.
    case ManageSettings = 'settings.manage';
    case ManageBilling = 'billing.manage';
    case ManageUsers = 'users.manage';

    // Editorial work on the corpus.
    case PublishRecords = 'records.publish';
    case VerifyRecords = 'records.verify';
    case DecideSubmissions = 'submissions.decide';
    case ManageTools = 'tools.manage';
    case SyncExternalData = 'external.sync';
    case RunJobs = 'jobs.run';
    case ManageSubscribers = 'subscribers.manage';

    // Reading. Separated from the above so an analyst can be given sight without reach.
    case ViewDashboard = 'dashboard.view';
    case ViewAudit = 'audit.view';
    case ViewAudience = 'audience.view';

    public function label(): string
    {
        return match ($this) {
            self::ManageSettings => 'Change platform settings',
            self::ManageBilling => 'Manage billing',
            self::ManageUsers => 'Manage users and roles',
            self::PublishRecords => 'Publish records',
            self::VerifyRecords => 'Verify records against their source',
            self::DecideSubmissions => 'Decide contributor submissions',
            self::ManageTools => 'Manage tools and downloads',
            self::SyncExternalData => 'Trigger external data syncs',
            self::RunJobs => 'Run scheduled jobs on demand',
            self::ManageSubscribers => 'Manage subscribers',
            self::ViewDashboard => 'View the dashboard',
            self::ViewAudit => 'View the audit log',
            self::ViewAudience => 'View subscribers and downloads',
        };
    }
}
