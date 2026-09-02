<?php

namespace Tests\Feature;

use App\Actions\Events\UploadEventFiles;
use App\Models\Event;
use App\Models\EventFile;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventFileTest extends TestCase
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
        return '/app/events/'.$this->event->slug.'/files'.$suffix;
    }

    private function text(string $name = 'notes.txt', string $content = 'Event meeting notes and useful details.'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    private function upload(): EventFile
    {
        $this->post($this->url(), ['category' => 'other', 'files' => [$this->text()]])->assertRedirect()->assertSessionHasNoErrors();

        return EventFile::latest('id')->firstOrFail();
    }

    public function test_multiple_files_are_private_and_attributed_by_server(): void
    {
        $this->postJson($this->url(), ['category' => 'photos', 'files' => [$this->photo(), $this->photo()], 'uploaded_by' => 999, 'event_id' => 999, 'caption' => 'A useful caption'])->assertOk();
        $this->assertDatabaseCount('event_files', 2);
        foreach (EventFile::all() as $file) {
            $this->assertSame($this->manager->id, $file->uploaded_by);
            $this->assertSame($this->event->id, $file->event_id);
            $this->assertArrayNotHasKey('storage_path', $file->toArray());
            Storage::disk('local')->assertExists($file->storage_path);
            $this->get($this->url('/'.$file->id.'/preview'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
            $this->get($this->url('/'.$file->id.'/download'))->assertOk()->assertDownload('photo.png');
        }
        $this->get($this->url())->assertOk()->assertSee('A useful caption')->assertDontSee('event-files/'.$this->event->id.'/');
        $this->assertDatabaseCount('event_activities', 2);
    }

    public function test_invalid_file_rejects_the_entire_batch(): void
    {
        foreach ([$this->text('bad.php'), $this->text('fake.png'), $this->text('fake.docx'), $this->text('page.svg', '<svg/>'), $this->text('active.txt', '<?php echo 1;')] as $invalid) {
            $this->post($this->url(), ['category' => 'other', 'files' => [$this->text(), $invalid]])->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('event_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_size_batch_count_and_total_size_limits(): void
    {
        $this->post($this->url(), ['category' => 'other', 'files' => [UploadedFile::fake()->create('large.txt', 2049)]])->assertSessionHasErrors('files.0');
        $this->post($this->url(), ['category' => 'other', 'files' => array_map(fn () => $this->text(), range(1, 6))])->assertSessionHasErrors('files');
        $this->post($this->url(), ['category' => 'other', 'files' => array_map(fn () => $this->text('notes.txt', str_repeat('x', 1800 * 1024)), range(1, 4))])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('event_files', 0);
    }

    public function test_photo_category_and_preview_require_an_image(): void
    {
        $this->post($this->url(), ['category' => 'photos', 'files' => [$this->text()]])->assertSessionHasErrors('files');
        $file = $this->upload();
        $this->get($this->url('/'.$file->id.'/preview'))->assertNotFound();
        $this->patch($this->url('/'.$file->id), ['category' => 'photos', 'display_order' => 0])->assertSessionHasErrors('category');
    }

    public function test_private_access_parent_binding_and_revocation(): void
    {
        $file = $this->upload();
        $other = Event::factory()->create();
        $this->get('/app/events/'.$other->slug.'/files/'.$file->id.'/download')->assertNotFound();
        $member = User::factory()->create();
        $this->signIn($member);
        $this->get($this->url())->assertForbidden();
        $this->get($this->url('/'.$file->id.'/download'))->assertForbidden();
        $this->post($this->url(), ['category' => 'other', 'files' => [$this->text()]])->assertForbidden();
        $this->event->update(['visibility' => 'selected_users']);
        $this->event->allowedUsers()->attach($member);
        $this->get($this->url('/'.$file->id.'/download'))->assertOk();
        $this->post($this->url(), ['category' => 'other', 'files' => [$this->text()]])->assertForbidden();
        $this->event->allowedUsers()->detach($member);
        $this->get($this->url('/'.$file->id.'/download'))->assertForbidden();
    }

    public function test_uploader_permission_does_not_grant_event_access_or_other_uploads(): void
    {
        $file = $this->upload();
        $member = User::factory()->create();
        $member->givePermissionTo('upload-event-files');
        $this->signIn($member);
        $this->post($this->url(), ['category' => 'other', 'files' => [$this->text()]])->assertForbidden();
        $this->event->update(['visibility' => 'all_users']);
        $own = $this->upload();
        $this->patch($this->url('/'.$file->id), ['caption' => 'No', 'category' => 'other', 'display_order' => 0])->assertForbidden();
        $this->delete($this->url('/'.$file->id), ['confirm' => 1])->assertForbidden();
        $this->patch($this->url('/'.$own->id), ['caption' => '<script>alert(1)</script>', 'category' => 'designs', 'display_order' => 17])->assertRedirect();
        $this->get($this->url())->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame(17, $own->fresh()->display_order);
        $own->update(['display_order' => 1000000]);
        $next = $this->upload();
        $this->assertSame(1000000, $next->display_order);
        $this->patch($this->url('/'.$next->id), ['caption' => 'Still editable', 'category' => 'other', 'display_order' => $next->display_order])->assertSessionHasNoErrors();
    }

    public function test_deletion_requires_confirmation_and_revokes_all_file_access(): void
    {
        $file = $this->upload();
        $this->delete($this->url('/'.$file->id))->assertSessionHasErrors('confirm');
        $this->delete($this->url('/'.$file->id), ['confirm' => 1])->assertRedirect();
        $this->assertSoftDeleted('event_files', ['id' => $file->id]);
        Storage::disk('local')->assertExists($file->storage_path);
        $this->get($this->url('/'.$file->id.'/download'))->assertNotFound();
        $this->get($this->url())->assertDontSee('notes.txt');
    }

    public function test_scanner_rejects_test_malware_and_fails_closed_in_production(): void
    {
        $this->post($this->url(), ['category' => 'other', 'files' => [$this->text('malware.txt', 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')]])->assertSessionHasErrors();
        $this->app->instance('env', 'production');
        try {
            app(UploadEventFiles::class)->handle($this->manager, $this->event, ['category' => 'other', 'files' => [$this->text()]]);
            $this->fail('Production upload must fail closed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
            $this->assertDatabaseCount('event_files', 0);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_deleted_event_missing_file_and_tampered_path_are_unavailable(): void
    {
        $file = $this->upload();
        $path = $file->storage_path;
        $file->update(['storage_path' => '../.env']);
        $this->get($this->url('/'.$file->id.'/download'))->assertNotFound();
        $file->update(['storage_path' => $path]);
        Storage::disk('local')->delete($path);
        $this->get($this->url('/'.$file->id.'/download'))->assertNotFound();
        $this->event->delete();
        $this->get($this->url())->assertNotFound();
    }

    public function test_filters_and_pagination_are_scoped_to_parent_event(): void
    {
        EventFile::factory()->count(25)->create(['event_id' => $this->event->id, 'uploaded_by' => $this->manager->id]);
        EventFile::factory()->create();
        $this->get($this->url())->assertOk()->assertViewHas('files', fn ($files) => $files->total() === 25 && $files->count() === 24);
        $this->get($this->url('?page=2'))->assertOk()->assertViewHas('files', fn ($files) => $files->count() === 1);
        $this->get($this->url('?category=photos'))->assertOk()->assertViewHas('files', fn ($files) => $files->total() === 0);
    }

    public function test_pdf_and_bounded_docx_packages_upload_but_unsafe_archives_do_not(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'event-docx-');
        try {
            $zip = new \ZipArchive;
            $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<Types/>');
            $zip->addFromString('word/document.xml', '<document>Event report</document>');
            $zip->close();
            $docx = UploadedFile::fake()->createWithContent('report.docx', file_get_contents($path));
            $pdf = $this->text('report.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
            $this->post($this->url(), ['category' => 'reports', 'files' => [$docx, $pdf]])->assertSessionHasNoErrors();
            $this->assertDatabaseCount('event_files', 2);
            $zip->open($path);
            $zip->addFromString('../payload.bin', 'unsafe package entry');
            $zip->close();
            $this->post($this->url(), ['category' => 'reports', 'files' => [UploadedFile::fake()->createWithContent('unsafe.docx', file_get_contents($path))]])->assertSessionHasErrors('files');
            $this->assertDatabaseCount('event_files', 2);
        } finally {
            unlink($path);
        }
    }

    public function test_a_storage_failure_never_creates_file_metadata(): void
    {
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::set('local', $disk);
        $this->post($this->url(), ['category' => 'other', 'files' => [$this->text()]])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('event_files', 0);
    }
}
