<?php

namespace Tests\Unit;

use App\Support\Links;
use Tests\TestCase;

/**
 * A change whose official source is one of our own pages was marked nofollow
 * like an outside citation, which told crawlers not to follow a link into the
 * site. The mark belongs on links that leave the site and nowhere else.
 */
class LinksTest extends TestCase
{
    public function test_nofollow_is_reserved_for_links_that_leave_the_site(): void
    {
        config(['app.url' => 'https://aipolicytracker.org']);

        $this->assertSame('noopener nofollow', Links::sourceRel('https://eur-lex.europa.eu/eli/reg/2024/1689/oj'));
        $this->assertSame('', Links::sourceRel('https://aipolicytracker.org/templates/eu-ai-act-article-50'));
        $this->assertSame('', Links::sourceRel('https://www.aipolicytracker.org/methodology'));
        $this->assertSame('', Links::sourceRel('/templates/eu-ai-act-article-50'));
        $this->assertSame('', Links::sourceRel(null));
        $this->assertSame('noopener nofollow', Links::sourceRel('https://aipolicytracker.org.example.net/x'));
    }
}
