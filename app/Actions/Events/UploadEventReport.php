<?php

namespace App\Actions\Events;

use App\Actions\Notifications\RecordWorkspaceNotice;
use App\Enums\EventReportType;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UploadEventReport
{
    public function handle(User $actor, Event $event, array $input): void
    {
        Gate::forUser($actor)->authorize('create', [EventReport::class, $event]);
        $data = Validator::make($input, [
            'type' => ['required', Rule::enum(EventReportType::class)],
            'title' => ['required', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => ['required', 'file', 'max:2048', 'extensions:pdf,docx,xlsx'],
        ])->validate();
        $existing = $event->reports()->where('type', $data['type'])->first();
        if ($existing) {
            Gate::forUser($actor)->authorize('update', $existing);
        }
        app(UploadEventFiles::class)->handle($actor, $event, ['category' => 'reports', 'files' => [$data['file']]], function (Event $locked, $files) use ($actor, $data) {
            $report = $locked->reports()->withTrashed()->where('type', $data['type'])->first();
            if ($report && ! $report->trashed()) {
                Gate::forUser($actor)->authorize('update', $report);
            } elseif ($report) {
                // Starting again never resurrects deleted historical versions.
                $report->restore();
                $report->update(['created_by' => $actor->id]);
            } else {
                $report = $locked->reports()->create(['type' => $data['type'], 'created_by' => $actor->id]);
            }
            $number = (int) $report->versions()->withTrashed()->max('version_number') + 1;
            $version = $report->versions()->create(['event_id' => $locked->id, 'event_file_id' => $files->sole()->id, 'version_number' => $number, 'title' => trim($data['title']), 'notes' => $data['notes'] ?? null]);
            $report->touch();
            app(RecordWorkspaceNotice::class)->handle('report_uploaded', 'report:'.$version->id, ['event_id' => $locked->id, 'report_version_id' => $version->id]);
            $locked->activities()->create(['actor_id' => $actor->id, 'action' => 'event.report_version_uploaded', 'metadata' => ['report_id' => $report->id, 'version_id' => $version->id, 'type' => $report->type->value], 'created_at' => now()]);
        });
    }
}
