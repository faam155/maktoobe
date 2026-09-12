<?php

namespace App\Support\Authorization;

final class Access
{
    public const SUPER_ADMINISTRATOR = 'Super Administrator';

    public const ADMINISTRATOR = 'Administrator';

    public const CONTENT_MANAGER = 'Content Manager';

    public const EVENT_MANAGER = 'Event Manager';

    public const STANDARD_USER = 'Standard User';

    public const PERMISSIONS = [
        'linkedin.dashboard.view', 'linkedin.posts.view', 'linkedin.posts.create', 'linkedin.posts.edit', 'linkedin.posts.delete', 'linkedin.metrics.manage', 'linkedin.import', 'linkedin.export', 'linkedin.targets.view', 'linkedin.targets.manage', 'linkedin.campaigns.manage', 'linkedin.audience.view', 'linkedin.ai.view', 'linkedin.ai.generate', 'linkedin.settings.manage', 'linkedin.sync',
        'access-admin', 'manage-users', 'create-users', 'edit-users', 'disable-users', 'delete-users',
        'manage-roles', 'manage-permissions', 'manage-prompts', 'publish-prompts', 'manage-categories',
        'manage-events', 'upload-event-files', 'manage-brand-guidelines', 'use-ai', 'manage-ai-settings',
        'view-reports', 'view-analytics', 'manage-system-settings', 'view-audit-logs',
    ];

    public const ROLE_PERMISSIONS = [
        self::SUPER_ADMINISTRATOR => self::PERMISSIONS,
        self::ADMINISTRATOR => [
            'access-admin', 'manage-users', 'create-users', 'edit-users', 'disable-users', 'delete-users',
            'manage-roles', 'manage-prompts', 'publish-prompts', 'manage-categories', 'manage-events',
            'upload-event-files', 'manage-brand-guidelines', 'use-ai', 'manage-ai-settings', 'view-reports',
            'view-analytics', 'view-audit-logs',
        ],
        self::CONTENT_MANAGER => ['manage-prompts', 'publish-prompts', 'manage-categories', 'manage-brand-guidelines', 'use-ai'],
        self::EVENT_MANAGER => ['manage-events', 'upload-event-files', 'use-ai'],
        self::STANDARD_USER => ['use-ai'],
    ];

    public static function isProtectedRole(string $name): bool
    {
        return $name === self::SUPER_ADMINISTRATOR;
    }
}
