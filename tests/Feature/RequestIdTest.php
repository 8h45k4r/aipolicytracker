<?php

namespace Tests\Feature;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * The reference an error page asks the reader to quote has to lead somewhere: the
 * same id is on the response, in the log context of whatever failed, and on the page.
 */
class RequestIdTest extends TestCase
{
    public function test_every_response_carries_a_request_id(): void
    {
        $response = $this->get('/up')->assertOk();

        $this->assertMatchesRegularExpression('/^[a-z0-9]{16}$/', $response->headers->get(AssignRequestId::HEADER));
    }

    public function test_the_error_page_quotes_the_id_that_the_log_carries(): void
    {
        Route::get('/__boom', fn () => throw new RuntimeException('boom'))->middleware('web');
        config(['app.debug' => false]);

        $response = $this->get('/__boom');

        $response->assertStatus(500);
        $id = $response->headers->get(AssignRequestId::HEADER);
        $this->assertNotEmpty($id);
        $response->assertSee('quote reference <code>'.$id.'</code>', false);
        $this->assertSame($id, Log::sharedContext()[AssignRequestId::ATTRIBUTE] ?? null);
    }

    public function test_a_well_formed_proxy_id_is_kept_and_a_malformed_one_is_replaced(): void
    {
        $kept = $this->withHeader(AssignRequestId::HEADER, 'abc123def456')->get('/up');
        $this->assertSame('abc123def456', $kept->headers->get(AssignRequestId::HEADER));

        $replaced = $this->withHeader(AssignRequestId::HEADER, '<script>x</script>')->get('/up');
        $this->assertMatchesRegularExpression('/^[a-z0-9]{16}$/', $replaced->headers->get(AssignRequestId::HEADER));
    }
}
