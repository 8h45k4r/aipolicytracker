<?php

namespace App\Services\Social;

use RuntimeException;

/** A post X refused or could not take. Retryable failures (rate limits, outages) are tried again later. */
final class XPostFailed extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
