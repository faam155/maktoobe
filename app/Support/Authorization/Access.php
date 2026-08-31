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
        'access-admin', 'manage-users', 'create-users', 'edit-users', 'disable-users', 'delete-users',
        'manage-roles', 'manage-permissions', 'manage-prompts', 'publish-prompts', 'manage-categories',
        'manage-events', 'upload-event-files', 'manage-brand-guidelines', 'use-ai', 'manage-ai-settings',
        'view-reports', 'view-analytics',
    ];

    public const ROLE_PERMISSIONS = [
        self::SUPER_ADMINISTRATOR => self::PERMISSIONS,
        self::ADMINISTRATOR => [
            'access-admin', 'manage-users', 'create-users', 'edit-users', 'disable-users', 'delete-users',
            'manage-roles', 'manage-prompts', 'publish-prompts', 'manage-categories', 'manage-events',
            'upload-event-files', 'manage-brand-guidelines', 'use-ai', 'manage-ai-settings', 'view-reports',
            'view-analytics',
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
