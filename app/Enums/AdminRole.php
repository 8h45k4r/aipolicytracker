<?php

namespace App\Enums;

/**
 * The fixed set of administrative roles.
 *
 * Owner is deliberately absent from this enum's storable values: it is derived from the
 * ADMIN_EMAILS environment list and is never written to the database. That keeps one property
 * worth keeping — a mistake in the users table, or write access to it, cannot create an owner
 * or remove the last one, and cannot lock everybody out of a running deployment. The roles
 * below are grantable in the admin UI by an owner; ownership itself moves only by editing the
 * environment and restarting.
 */
enum AdminRole: string
{
    case Editor = 'editor';
    case Reviewer = 'reviewer';
    case Analyst = 'analyst';

    public function label(): string
    {
        return match ($this) {
            self::Editor => 'Editor',
            self::Reviewer => 'Reviewer',
            self::Analyst => 'Analyst',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Editor => 'Works on the corpus: publishes and verifies records, decides submissions, manages tools and triggers syncs. Cannot change settings, billing or users.',
            self::Reviewer => 'Confirms records against their official source and decides submissions. Reads the dashboard. Changes nothing else.',
            self::Analyst => 'Reads only: dashboard, audit log, subscribers and downloads. Cannot change any record.',
        };
    }

    /** @return list<AdminCapability> */
    public function capabilities(): array
    {
        return match ($this) {
            self::Editor => [
                AdminCapability::PublishRecords,
                AdminCapability::VerifyRecords,
                AdminCapability::DecideSubmissions,
                AdminCapability::ManageTools,
                AdminCapability::SyncExternalData,
                AdminCapability::ManageSubscribers,
                AdminCapability::ViewDashboard,
                AdminCapability::ViewAudience,
            ],
            self::Reviewer => [
                AdminCapability::VerifyRecords,
                AdminCapability::DecideSubmissions,
                AdminCapability::ViewDashboard,
            ],
            self::Analyst => [
                AdminCapability::ViewDashboard,
                AdminCapability::ViewAudit,
                AdminCapability::ViewAudience,
            ],
        };
    }

    public function has(AdminCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
