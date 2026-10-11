<?php

namespace Tests\Feature;

use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\SchemaValidator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** A duty still citing "reviewer to cite…" cannot be published as verified. */
class PlaceholderCitationTest extends TestCase
{
    public function test_a_verified_obligation_with_a_placeholder_citation_is_refused(): void
    {
        $dir = sys_get_temp_dir().'/aip-placeholder-'.uniqid();
        File::copyDirectory(base_path('data'), $dir);
        $file = $dir.'/policies/uae/uae-personal-data-protection-law.yaml';
        $validate = fn () => (new PolicyDataValidator(new PolicyDataRepository($dir), new SchemaValidator($dir.'/schema')))->run();

        try {
            $this->assertSame([], $validate(), 'the committed data marks these duties pending review');

            File::put($file, str_replace("    review_status: pending_review\n", '', File::get($file)));
            $errors = $validate();
            $this->assertStringContainsString('uae-personal-data-protection-law.yaml', implode("\n", array_keys($errors)));
            $this->assertStringContainsString('still a placeholder', implode("\n", array_merge(...array_values($errors))));
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
