<?php

namespace Tests\Feature;

use App\Actions\Events\UploadEventReport;
use App\Models\Event;
use App\Models\EventFile;
use App\Models\EventReportVersion;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class EventReportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
        $this->manager = User::factory()->create();
        $this->manager->assignRole(Access::EVENT_MANAGER);
        $this->event = Event::factory()->create(['organizer_id' => $this->manager->id, 'visibility' => 'private']);
        $this->signIn($this->manager);
    }

    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.confirmed_at' => time()]);
    }

    private function url(string $suffix = ''): string
    {
        return '/app/events/'.$this->event->slug.'/reports'.$suffix;
    }

    private function payload(string $type = 'PRE_EVENT', string $title = 'Preparation report'): array
    {
        return ['type' => $type, 'title' => $title, 'notes' => 'Version notes', 'file' => UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")];
    }

    private function upload(string $type = 'PRE_EVENT'): EventReportVersion
    {
        $this->post($this->url(), $this->payload($type))->assertRedirect()->assertSessionHasNoErrors();

        return EventReportVersion::latest('id')->firstOrFail();
    }

    private function download(EventReportVersion $version): string
    {
        return $this->url('/'.$version->event_report_id.'/versions/'.$version->id.'/download');
    }

    public function test_uploads_preserve_history_and_identify_current_version_for_each_type(): void
    {
        $first = $this->upload();
        $bytes = Storage::disk('local')->get($first->file->storage_path);
        $this->post($this->url(), $this->payload('PRE_EVENT', 'Revised preparation'))->assertSessionHasNoErrors();
        $post = $this->upload('POST_EVENT');
        $report = $first->report->fresh();
        $this->assertSame(2, $report->currentVersion->version_number);
        $this->assertSame('Revised preparation', $report->currentVersion->title);
        $this->assertSame(1, $post->version_number);
        $this->assertSame($bytes, Storage::disk('local')->get($first->file->storage_path));
        $this->assertSame($this->manager->id, $first->file->uploaded_by);
        $this->assertDatabaseCount('event_reports', 2);
        $this->assertDatabaseCount('event_report_versions', 3);
        $this->get($this->url())->assertOk()->assertSee('Current version')->assertSee('Previous version')->assertSee('Revised preparation');
        $this->get($this->download($first))->assertOk()->assertDownload('report.pdf')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_reports_cannot_be_mutated_or_listed_as_generic_files(): void
    {
        $version = $this->upload();
        $url = '/app/events/'.$this->event->slug.'/files/'.$version->event_file_id;
        $this->patch($url, ['category' => 'other', 'caption' => 'Changed', 'display_order' => 0])->assertForbidden();
        $this->delete($url, ['confirm' => 1])->assertForbidden();
        $this->get('/app/events/'.$this->event->slug.'/files')->assertDontSee('report.pdf');
        $this->get($url.'/download')->assertOk();
    }

    public function test_view_download_and_upload_require_current_event_access(): void
    {
        $version = $this->upload();
        $member = User::factory()->create();
        $this->signIn($member);
        $this->get($this->url())->assertForbidden();
        $this->get($this->download($version))->assertForbidden();
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->event->update(['visibility' => 'selected_users']);
        $this->event->allowedUsers()->attach($member);
        $this->get($this->url())->assertOk();
        $this->get($this->download($version))->assertOk();
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->event->allowedUsers()->detach($member);
        $this->get($this->download($version))->assertForbidden();
    }

    public function test_uploaders_cannot_replace_or_delete_another_owners_report(): void
    {
        $this->event->update(['visibility' => 'all_users']);
        $owner = User::factory()->create();
        $owner->givePermissionTo('upload-event-files');
        $this->signIn($owner);
        $first = $this->upload();
        $another = User::factory()->create();
        $another->givePermissionTo('upload-event-files');
        $this->signIn($another);
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->delete($this->url('/'.$first->event_report_id), ['confirm' => 1])->assertForbidden();
        $this->upload('POST_EVENT');
        $this->signIn($owner);
        $this->upload();
        $this->signIn($this->manager);
        $this->upload();
        $this->assertSame(3, $first->report->currentVersion->version_number);
    }

    public function test_invalid_types_formats_and_oversized_files_create_no_versions(): void
    {
        $this->post($this->url(), $this->payload('OTHER'))->assertSessionHasErrors('type');
        $data = $this->payload();
        $data['file'] = UploadedFile::fake()->createWithContent('wrong.xlsx', 'not an archive');
        $this->post($this->url(), $data)->assertSessionHasErrors();
        $data['file'] = UploadedFile::fake()->createWithContent('notes.txt', 'notes');
        $this->post($this->url(), $data)->assertSessionHasErrors('file');
        $data['file'] = UploadedFile::fake()->create('large.pdf', 2049);
        $this->post($this->url(), $data)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('event_report_versions', 0);
        $this->assertDatabaseCount('event_files', 0);
    }

    public function test_xlsx_and_docx_packages_are_supported_and_macro_entries_rejected(): void
    {
        foreach (['xlsx' => 'xl/workbook.xml', 'docx' => 'word/document.xml'] as $extension => $entry) {
            $path = tempnam(sys_get_temp_dir(), 'report-');
            try {
                $zip = new ZipArchive;
                $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $zip->addFromString('[Content_Types].xml', '<Types/>');
                $zip->addFromString($entry, '<document/>');
                $zip->close();
                $data = $this->payload();
                $data['file'] = UploadedFile::fake()->createWithContent('report.'.$extension, file_get_contents($path));
                $this->post($this->url(), $data)->assertSessionHasNoErrors();
                $version = EventReportVersion::latest('id')->firstOrFail();
                $this->get($this->download($version))->assertOk()->assertDownload('report.'.$extension);
                $zip->open($path);
                $zip->addFromString('xl/vbaProject.bin', 'macro');
                $zip->close();
                $data['file'] = UploadedFile::fake()->createWithContent('macro.'.$extension, file_get_contents($path));
                $this->post($this->url(), $data)->assertSessionHasErrors();
            } finally {
                unlink($path);
            }
        }
        $this->assertDatabaseCount('event_report_versions', 2);
    }

    public function test_delete_revokes_all_versions_and_reupload_does_not_restore_them(): void
    {
        $first = $this->upload();
        $this->upload();
        $this->delete($this->url('/'.$first->event_report_id))->assertSessionHasErrors('confirm');
        $this->delete($this->url('/'.$first->event_report_id), ['confirm' => 1])->assertRedirect();
        $this->get($this->download($first))->assertNotFound();
        $this->get('/app/events/'.$this->event->slug.'/files/'.$first->event_file_id.'/download')->assertNotFound();
        Storage::disk('local')->assertExists($first->file()->withTrashed()->first()->storage_path);
        $next = $this->upload();
        $this->assertSame(3, $next->version_number);
        $this->assertCount(1, $next->report->versions);
        $this->get($this->download($first))->assertNotFound();
    }

    public function test_parent_ids_cannot_be_forged_and_notes_are_escaped(): void
    {
        $data = $this->payload();
        $data['notes'] = '<script>alert(1)</script>';
        $data['event_id'] = 999;
        $data['version_number'] = 77;
        $data['uploaded_by'] = 999;
        $this->post($this->url(), $data)->assertSessionHasNoErrors();
        $version = EventReportVersion::firstOrFail();
        $this->assertSame(1, $version->version_number);
        $this->assertSame($this->event->id, $version->event_id);
        $this->get($this->url())->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $other = Event::factory()->create();
        $this->get('/app/events/'.$other->slug.'/reports/'.$version->event_report_id.'/versions/'.$version->id.'/download')->assertNotFound();
    }

    public function test_database_rejects_cross_event_report_file_links(): void
    {
        $version = $this->upload();
        $otherFile = EventFile::factory()->create();
        $this->expectException(QueryException::class);
        $version->report->versions()->create(['event_id' => $this->event->id, 'event_file_id' => $otherFile->id, 'version_number' => 2, 'title' => 'Invalid link']);
    }

    public function test_failed_version_insert_rolls_back_metadata_and_removes_new_bytes(): void
    {
        EventReportVersion::creating(fn () => throw new \RuntimeException('Simulated metadata failure'));
        try {
            app(UploadEventReport::class)->handle($this->manager, $this->event, $this->payload());
            $this->fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated metadata failure', $exception->getMessage());
            $this->assertDatabaseCount('event_reports', 0);
            $this->assertDatabaseCount('event_files', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            EventReportVersion::flushEventListeners();
        }
    }

    public function test_history_is_paginated_without_hiding_the_current_summary(): void
    {
        for ($i = 0; $i < 11; $i++) {
            app(UploadEventReport::class)->handle($this->manager, $this->event, $this->payload());
        }
        $this->get($this->url('?pre_page=2'))->assertOk()->assertViewHas('sections', fn ($sections) => $sections[0]['versions']->count() === 1 && $sections[0]['report']->currentVersion->version_number === 11)->assertSee('Version 11');
    }
}
