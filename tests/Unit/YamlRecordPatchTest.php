<?php

namespace Tests\Unit;

use App\Services\Review\YamlRecordPatch;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class YamlRecordPatchTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'yaml');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_a_whole_file_record_keeps_everything_but_the_named_keys(): void
    {
        file_put_contents($this->file, "slug: a\n# the summary\nsummary: >\n  Two lines\n  of text.\nreview_status: pending_review\nreviewed_by: null\n");

        YamlRecordPatch::apply($this->file, ['review_status' => 'verified', 'reviewed_by' => 'Jane Doe', 'last_verified_at' => '2026-09-26']);

        $text = file_get_contents($this->file);
        $this->assertStringContainsString("# the summary\nsummary: >\n  Two lines\n  of text.\n", $text);
        $this->assertStringContainsString("review_status: verified\n", $text);
        $this->assertStringContainsString("reviewed_by: 'Jane Doe'\n", $text);
        $this->assertStringEndsWith("last_verified_at: '2026-09-26'\n", $text);
        $this->assertSame('verified', Yaml::parse($text)['review_status']);
    }

    public function test_one_item_of_a_list_is_patched_and_its_neighbours_are_not(): void
    {
        file_put_contents($this->file, "changes:\n  - slug: one\n    title: One\n    review_status: pending_review\n\n  - slug: two\n    title: Two\n    review_status: pending_review\n    reviewed_by: null\n  - slug: three\n    review_status: pending_review\n");

        $this->assertTrue(YamlRecordPatch::apply($this->file, ['review_status' => 'verified', 'reviewed_by' => 'Jane Doe', 'last_verified_at' => '2026-09-26'], 'two'));

        $parsed = Yaml::parse(file_get_contents($this->file))['changes'];
        $this->assertSame('pending_review', $parsed[0]['review_status']);
        $this->assertSame(['slug' => 'two', 'title' => 'Two', 'review_status' => 'verified', 'reviewed_by' => 'Jane Doe', 'last_verified_at' => '2026-09-26'], $parsed[1]);
        $this->assertSame('pending_review', $parsed[2]['review_status']);
        $this->assertArrayNotHasKey('reviewed_by', $parsed[2]);
    }

    public function test_a_missing_key_on_the_first_item_lands_before_the_blank_line_that_separates_items(): void
    {
        file_put_contents($this->file, "changes:\n  - slug: one\n    review_status: pending_review\n\n  - slug: two\n    review_status: pending_review\n");

        YamlRecordPatch::apply($this->file, ['reviewed_by' => 'Jane Doe'], 'one');

        $parsed = Yaml::parse(file_get_contents($this->file))['changes'];
        $this->assertSame('Jane Doe', $parsed[0]['reviewed_by']);
        $this->assertArrayNotHasKey('reviewed_by', $parsed[1]);
    }

    public function test_an_unknown_slug_changes_nothing(): void
    {
        file_put_contents($this->file, "changes:\n  - slug: one\n    review_status: pending_review\n");

        $this->assertFalse(YamlRecordPatch::apply($this->file, ['review_status' => 'verified'], 'nine'));
        $this->assertSame("changes:\n  - slug: one\n    review_status: pending_review\n", file_get_contents($this->file));
    }
}
