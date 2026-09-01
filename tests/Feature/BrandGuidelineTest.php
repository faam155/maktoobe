<?php

namespace Tests\Feature;

use App\Contracts\AiProvider;
use App\Models\AiRequest;
use App\Models\BrandGuideline;
use App\Models\BrandGuidelineVersion;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Fixtures\FakeAiProvider;
use Tests\TestCase;
use ZipArchive;

class BrandGuidelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Storage::fake('local');
        $this->app->bind(AiProvider::class, FakeAiProvider::class);
        FakeAiProvider::reset();
        config(['ai.models' => ['gpt-test'], 'ai.default_model' => 'gpt-test', 'ai.role_models' => []]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.confirmed_at' => time()]);
    }

    private function textFile(string $name = 'brand.txt', string $content = 'Use a clear, calm and inclusive brand voice.'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_authorized_user_uploads_private_guideline_and_downloads_it(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $this->signIn($admin)->post('/admin/brand-guidelines', ['title' => 'Corporate Voice', 'description' => 'Primary voice guide', 'version' => '1.0', 'file' => $this->textFile()])->assertRedirect();
        $version = BrandGuidelineVersion::firstOrFail();
        $this->assertSame($admin->id, $version->uploaded_by);
        $this->assertSame('ready', $version->extraction_status);
        $this->assertSame('clean', $version->scan_status);
        $this->assertStringStartsWith('brand-guidelines/', $version->storage_path);
        Storage::disk('local')->assertExists($version->storage_path);
        $this->assertFileDoesNotExist(public_path('storage/'.$version->storage_path));
        $this->get('/admin/brand-guideline-versions/'.$version->id.'/download')->assertOk()->assertHeader('x-content-type-options', 'nosniff');
        $this->assertDatabaseHas('account_audits', ['actor_id' => $admin->id, 'action' => 'brand_guideline.version_uploaded']);
    }

    public function test_version_history_and_single_active_version_are_maintained(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $this->signIn($admin)->post('/admin/brand-guidelines', ['title' => 'Voice', 'version' => '1.0', 'file' => $this->textFile('v1.txt', 'Voice one')]);
        $guideline = BrandGuideline::firstOrFail();
        $this->post('/admin/brand-guidelines/'.$guideline->id.'/versions', ['title' => 'Voice Updated', 'description' => 'Current description', 'version' => '2.0', 'file' => $this->textFile('v2.txt', 'Voice two')])->assertRedirect();
        $first = $guideline->versions()->where('version', '1.0')->firstOrFail();
        $second = $guideline->versions()->where('version', '2.0')->firstOrFail();
        $this->patch('/admin/brand-guideline-versions/'.$first->id.'/status', ['active' => true])->assertRedirect();
        $this->patch('/admin/brand-guideline-versions/'.$second->id.'/status', ['active' => true])->assertRedirect();
        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame('Voice Updated', $guideline->fresh()->title);
        $this->post('/admin/brand-guidelines/'.$guideline->id.'/versions', ['title' => 'Voice', 'version' => '2.0', 'file' => $this->textFile('duplicate.txt')])->assertSessionHasErrors('version');
    }

    public function test_file_validation_rejects_spoofed_unsupported_and_oversized_files(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $this->signIn($admin)->post('/admin/brand-guidelines', ['title' => 'Bad', 'version' => '1', 'file' => UploadedFile::fake()->createWithContent('bad.txt', "MZ\0\0binary")])->assertSessionHasErrors('file');
        $this->post('/admin/brand-guidelines', ['title' => 'Bad', 'version' => '1', 'file' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('file');
        $this->post('/admin/brand-guidelines', ['title' => 'Bad', 'version' => '1', 'file' => UploadedFile::fake()->create('large.pdf', 11000, 'application/pdf')])->assertSessionHasErrors('file');
        $this->post('/admin/brand-guidelines', ['title' => 'Bad', 'version' => '1', 'file' => $this->textFile('unsafe.txt', 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('brand_guidelines', 0);
    }

    public function test_pdf_docx_and_image_signatures_are_accepted_as_stored_only_versions(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $this->signIn($admin);
        $paths = [];
        try {
            $pdf = $paths[] = tempnam(sys_get_temp_dir(), 'brand-pdf-');
            file_put_contents($pdf, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
            $png = $paths[] = tempnam(sys_get_temp_dir(), 'brand-png-');
            file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
            $docx = $paths[] = tempnam(sys_get_temp_dir(), 'brand-docx-');
            $zip = new ZipArchive;
            $zip->open($docx, ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
            $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
            $zip->close();

            foreach ([['guide.pdf', $pdf, 'application/pdf'], ['logo.png', $png, 'image/png'], ['guide.docx', $docx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']] as $index => [$name, $path, $mime]) {
                $this->post('/admin/brand-guidelines', ['title' => 'Format '.$index, 'version' => '1', 'file' => new UploadedFile($path, $name, $mime, null, true)])->assertRedirect()->assertSessionHasNoErrors();
            }
            $this->assertSame(3, BrandGuidelineVersion::where('extraction_status', 'not_supported')->count());
        } finally {
            foreach ($paths as $path) {
                @unlink($path);
            }
        }
    }

    public function test_every_management_and_download_endpoint_enforces_permission(): void
    {
        $standard = $this->user(Access::STANDARD_USER);
        $guideline = BrandGuideline::create(['title' => 'Private', 'created_by' => $standard->id]);
        $version = $guideline->versions()->create(['version' => '1', 'storage_path' => 'brand-guidelines/private.txt', 'original_name' => 'private.txt', 'extension' => 'txt', 'mime_type' => 'text/plain', 'file_size' => 5, 'uploaded_by' => $standard->id]);
        Storage::disk('local')->put($version->storage_path, 'voice');
        $this->signIn($standard)->get('/admin/brand-guidelines')->assertForbidden();
        $this->get('/admin/brand-guidelines/'.$guideline->id)->assertForbidden();
        $this->post('/admin/brand-guidelines', [])->assertForbidden();
        $this->post('/admin/brand-guidelines/'.$guideline->id.'/versions', [])->assertForbidden();
        $this->patch('/admin/brand-guideline-versions/'.$version->id.'/status', ['active' => true])->assertForbidden();
        $this->get('/admin/brand-guideline-versions/'.$version->id.'/download')->assertForbidden();
    }

    public function test_content_manager_permission_grants_only_brand_administration_access(): void
    {
        $manager = $this->user(Access::CONTENT_MANAGER);
        $this->signIn($manager)->get('/admin/brand-guidelines')->assertOk();
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_ai_request_explicitly_selects_and_snapshots_active_text_context(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $this->signIn($admin)->post('/admin/brand-guidelines', ['title' => 'Voice Guide', 'version' => '3', 'file' => $this->textFile('voice.txt', 'Always use direct and respectful language.')]);
        $version = BrandGuidelineVersion::firstOrFail();
        $this->patch('/admin/brand-guideline-versions/'.$version->id.'/status', ['active' => true]);
        $user = $this->user(Access::STANDARD_USER);
        $payload = ['model' => 'gpt-test', 'content' => 'Write an update.', 'use_brand_guidelines' => '1', 'client_operation_id' => (string) Str::uuid()];
        $this->signIn($user)->post('/app/assistant', $payload)->assertRedirect();
        $request = AiRequest::firstOrFail();
        $this->assertSame($version->id, $request->brand_guideline_version_id);
        $this->assertTrue($request->settings_snapshot['use_brand_guidelines']);
        $this->assertSame('developer', FakeAiProvider::$calls[0]['messages'][0]['role']);
        $this->assertStringContainsString('Always use direct and respectful language.', FakeAiProvider::$calls[0]['messages'][0]['content']);
    }

    public function test_ai_rejects_requested_context_when_no_active_extractable_version_exists(): void
    {
        $user = $this->user(Access::STANDARD_USER);
        $this->signIn($user)->post('/app/assistant', ['model' => 'gpt-test', 'content' => 'Write.', 'use_brand_guidelines' => '1', 'client_operation_id' => (string) Str::uuid()])->assertSessionHasErrors('use_brand_guidelines');
        $this->assertDatabaseCount('ai_requests', 0);
    }
}
