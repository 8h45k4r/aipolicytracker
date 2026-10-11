<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Fonts are self-hosted. A font CDN sees every visitor's address and page, which the
 * privacy notice does not promise, so no source or built file may name one.
 */
class NoThirdPartyFontsTest extends TestCase
{
    public function test_no_source_or_built_file_loads_a_font_from_a_cdn(): void
    {
        $root = dirname(__DIR__, 2);
        $hits = [];
        foreach (['resources', 'public/build'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (! preg_match('/\.(php|css|scss|js|jsx|html)$/', $file->getFilename())) {
                    continue;
                }
                if (preg_match('/fonts\.(googleapis|gstatic|bunny)\.(com|net)/', (string) file_get_contents($file->getPathname()))) {
                    $hits[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }
        $this->assertSame([], $hits);
    }
}
