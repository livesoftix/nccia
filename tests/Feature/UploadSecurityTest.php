<?php

namespace Tests\Feature;

use App\Http\Middleware\InspectUploads;
use App\Services\{UploadScanner, UploadSecurity};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['security.uploads.scan_required' => false]);
        Route::post('/api/security-upload-test', fn () => response()->json(['stored' => true]))
            ->middleware(InspectUploads::class);
    }

    public function test_spoofed_executable_cannot_reach_the_upload_controller(): void
    {
        $this->postJson('/api/security-upload-test', ['nested' => [
            UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "unsafe";'),
        ]])->assertUnprocessable()->assertJsonValidationErrors('nested.0')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_upload_batch_and_file_size_limits_are_enforced_before_persistence(): void
    {
        config(['security.uploads.max_files' => 1]);
        $this->postJson('/api/security-upload-test', ['files' => [
            UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg'),
        ]])->assertUnprocessable()->assertJsonValidationErrors('files');
        config(['security.uploads.max_files' => 100, 'security.uploads.max_file_bytes' => 10]);
        $this->postJson('/api/security-upload-test', ['file' => UploadedFile::fake()->image('image.jpg')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_generic_zip_and_macro_documents_are_refused_but_safe_docx_is_recognized(): void
    {
        $this->assertNull(UploadSecurity::detectedExtension($this->archive(['file.txt' => 'test'])));
        $document = ['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<document/>'];
        $this->assertSame('docx', UploadSecurity::detectedExtension($this->archive($document)));
        $this->assertNull(UploadSecurity::detectedExtension($this->archive($document + ['word/vbaProject.bin' => 'macro'])));
        $this->assertNull(UploadSecurity::detectedExtension($this->archive($document + ['../outside.txt' => 'traversal'])));
    }

    public function test_archives_cannot_contain_symlinks_or_expand_beyond_the_ratio_limit(): void
    {
        $document = ['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<document/>'];
        $this->assertNull(UploadSecurity::detectedExtension($this->archive($document + ['word/large.xml' => str_repeat('A', 2 * 1024 * 1024)])));
        $this->assertNull(UploadSecurity::detectedExtension($this->archive($document + ['word/link' => '/private'], 'word/link')));
    }

    public function test_image_pixel_limit_is_enforced_without_decoding_the_image(): void
    {
        config(['security.uploads.max_image_pixels' => 100]);
        $this->assertNull(UploadSecurity::detectedExtension(UploadedFile::fake()->image('large.jpg', 20, 20)));
    }

    public function test_scanner_infection_blocks_upload_before_controller_runs(): void
    {
        config(['security.uploads.scan_required' => true]);
        $this->app->instance(UploadScanner::class, $this->scanner('exit(1);'));
        $this->postJson('/api/security-upload-test', ['file' => UploadedFile::fake()->image('image.jpg')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_unavailable_scanner_fails_closed_without_exposing_output(): void
    {
        config(['security.uploads.scan_required' => true]);
        $this->app->instance(UploadScanner::class, $this->scanner('fwrite(STDOUT, "private scanner details"); exit(2);'));
        $this->postJson('/api/security-upload-test', ['file' => UploadedFile::fake()->image('image.jpg')])
            ->assertStatus(503)->assertDontSee('private scanner details')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_missing_scanner_binary_fails_closed(): void
    {
        config(['security.uploads.scan_required' => true, 'security.uploads.scanner_binary' => 'nccia-nonexistent-test-scanner']);
        $this->expectException(HttpException::class);
        app(UploadScanner::class)->scan(UploadedFile::fake()->image('test.jpg'), 'file');
    }

    public function test_scanner_timeout_is_bounded_and_fails_closed(): void
    {
        config(['security.uploads.scan_required' => true, 'security.uploads.scan_timeout_seconds' => 0.1]);
        $start = microtime(true);
        try {
            $this->scanner('usleep(2000000);')->scan(UploadedFile::fake()->image('test.jpg'), 'file');
            $this->fail('A timed-out scan must fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertLessThan(1.5, microtime(true) - $start);
        }
    }

    public function test_clean_scanner_allows_upload(): void
    {
        config(['security.uploads.scan_required' => true]);
        $this->app->instance(UploadScanner::class, $this->scanner('exit(0);'));
        $this->postJson('/api/security-upload-test', ['file' => UploadedFile::fake()->image('image.jpg')])
            ->assertOk()->assertJson(['stored' => true]);
    }

    private function scanner(string $code): UploadScanner
    {
        return new class($code) extends UploadScanner {
            public function __construct(private string $code) {}
            protected function process(string $path): Process
            {
                return new Process([PHP_BINARY, '-r', $this->code]);
            }
        };
    }

    private function archive(array $entries, ?string $symlink = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'nccia-upload-test-');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) $zip->addFromString($name, $content);
        if ($symlink) $zip->setExternalAttributesName($symlink, \ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);
        return UploadedFile::fake()->createWithContent('document.docx', $bytes);
    }
}
