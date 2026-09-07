<?php

namespace App\Services\Audit;

use App\Support\Authorization\Access;

class SafeAuditMetadata
{
    public function clean(array $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (in_array($key, ['user_id', 'role_id', 'prompt_id', 'source_prompt_id', 'event_id', 'source_event_id', 'file_id', 'report_id', 'version_id', 'guideline_id', 'category_id', 'notice_id', 'communication_id', 'revision_id', 'revision'], true) && filter_var($value, FILTER_VALIDATE_INT) !== false && $value > 0) {
                $result[$key] = (int) $value;
            } elseif (in_array($key, ['email_changed', 'phone_changed', 'is_active'], true) && is_bool($value)) {
                $result[$key] = $value;
            } elseif (in_array($key, ['status', 'from', 'to', 'visibility', 'type', 'category', 'direction'], true) && is_string($value) && in_array($value, ['active', 'disabled', 'pending', 'draft', 'published', 'archived', 'planned', 'confirmed', 'in_progress', 'completed', 'cancelled', 'all_users', 'private', 'selected_users', 'selected_roles', 'PRE_EVENT', 'POST_EVENT', 'photos', 'reports', 'communications', 'designs', 'other', 'up', 'down'], true)) {
                $result[$key] = $value;
            } elseif (in_array($key, ['before_ids', 'after_ids'], true) && is_array($value)) {
                $result[$key] = array_values(array_map('intval', array_filter($value, fn ($id) => is_scalar($id) && filter_var($id, FILTER_VALIDATE_INT) !== false && $id > 0)));
            } elseif ($key === 'permissions' && is_array($value)) {
                $result[$key] = array_values(array_intersect(Access::PERMISSIONS, array_filter($value, 'is_string')));
            } elseif (in_array($key, ['before', 'after'], true) && is_array($value)) {
                $result[$key] = $this->clean($value);
            } elseif ($key === 'changed_keys' && is_array($value)) {
                $result[$key] = array_values(array_intersect(['provider', 'default_model', 'models', 'role_models', 'max_output_tokens', 'temperature', 'timeout', 'connect_timeout', 'timezone', 'locale', 'registration_status', 'session_lifetime', 'session_secure'], array_filter($value, 'is_string')));
            }
        }

        return $result;
    }
}
