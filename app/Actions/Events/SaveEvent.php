<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveEvent
{
    public function handle(User $actor, array $input, ?Event $event = null): Event
    {
        Gate::forUser($actor)->authorize($event ? 'update' : 'create', $event ?? Event::class);
        $creating = $event === null;
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', Rule::exists('event_categories', 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:1000-01-02', 'before:9999-12-30'], 'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:1000-01-02', 'before:9999-12-30'],
            'timezone' => ['required', 'timezone:all'], 'location' => ['nullable', 'string', 'max:255'],
            'organizer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'visibility' => ['required', Rule::enum(EventVisibility::class)],
            'status' => [$creating ? 'required' : 'nullable', $creating ? Rule::in(['draft', 'planned', 'confirmed']) : Rule::enum(EventStatus::class)],
            'user_ids' => ['nullable', 'array', 'max:250'], 'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'role_ids' => ['nullable', 'array', 'max:50'], 'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ])->after(function ($validator) use ($input) {
            try {
                $start = Carbon::createFromFormat('!Y-m-d\TH:i', (string) ($input['starts_at'] ?? ''), (string) ($input['timezone'] ?? 'UTC'));
                $end = Carbon::createFromFormat('!Y-m-d\TH:i', (string) ($input['ends_at'] ?? ''), (string) ($input['timezone'] ?? 'UTC'));
                if ($start->format('Y-m-d\TH:i') !== ($input['starts_at'] ?? '')) {
                    $validator->errors()->add('starts_at', __('events.invalid_date'));
                }
                if ($end->format('Y-m-d\TH:i') !== ($input['ends_at'] ?? '')) {
                    $validator->errors()->add('ends_at', __('events.invalid_date'));
                }
                if ($end->lte($start)) {
                    $validator->errors()->add('ends_at', __('events.ends_after_start'));
                }
            } catch (\Throwable) {
            }
            $visibility = $input['visibility'] ?? null;
            if ($visibility === EventVisibility::SelectedUsers->value && empty($input['user_ids'])) {
                $validator->errors()->add('user_ids', __('events.select_users'));
            }
            if ($visibility === EventVisibility::SelectedRoles->value && empty($input['role_ids'])) {
                $validator->errors()->add('role_ids', __('events.select_roles'));
            }
        })->validate();

        return DB::transaction(function () use ($actor, $event, $data, $creating) {
            if (! $creating) {
                $event = Event::lockForUpdate()->findOrFail($event->id);
            }
            $timezone = $data['timezone'];
            $attributes = [
                'title' => trim($data['title']), 'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'category_id' => $data['category_id'] ?? null,
                'starts_at' => Carbon::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], $timezone)->utc(),
                'ends_at' => Carbon::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], $timezone)->utc(),
                'timezone' => $timezone, 'location' => filled($data['location'] ?? null) ? trim($data['location']) : null,
                'organizer_id' => $data['organizer_id'], 'visibility' => $data['visibility'], 'updated_by' => $actor->id,
            ];
            if ($creating) {
                $attributes += ['slug' => Str::substr(Str::slug($data['title']), 0, 170).'-'.Str::lower(Str::random(16)), 'status' => $data['status'], 'created_by' => $actor->id];
                $event = Event::create($attributes);
            } else {
                $event->update($attributes);
            }
            $userIds = $data['visibility'] === EventVisibility::SelectedUsers->value ? ($data['user_ids'] ?? []) : [];
            $roleIds = $data['visibility'] === EventVisibility::SelectedRoles->value ? ($data['role_ids'] ?? []) : [];
            $event->allowedUsers()->sync(collect($userIds)->mapWithKeys(fn ($id) => [$id => ['granted_by' => $actor->id, 'created_at' => now()]])->all());
            $event->allowedRoles()->sync(collect($roleIds)->mapWithKeys(fn ($id) => [$id => ['granted_by' => $actor->id, 'created_at' => now()]])->all());
            EventActivity::create(['event_id' => $event->id, 'actor_id' => $actor->id, 'action' => $creating ? 'event.created' : 'event.updated', 'metadata' => ['visibility' => $event->visibility->value], 'created_at' => now()]);

            return $event->fresh();
        });
    }
}
