<?php

namespace Tests\Unit;

use App\Models\ExternalIncidentReport;
use PHPUnit\Framework\TestCase;

/** Report authors are republished, so a mailbox AIID lists in place of a name is dropped. */
class ReportAuthorsTest extends TestCase
{
    public function test_email_addresses_are_dropped_and_names_kept(): void
    {
        $this->assertSame(
            ['Jane Reporter', 'Newsroom'],
            ExternalIncidentReport::cleanAuthors(['Jane Reporter', 'someone@example.com', '  ', ' Newsroom ', 'x@y.co']),
        );
        $this->assertCount(6, ExternalIncidentReport::cleanAuthors(array_fill(0, 9, 'Name')));
    }
}
