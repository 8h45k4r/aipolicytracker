<?php

namespace App\Services\ExternalData;

use RuntimeException;

/**
 * The AI Incident Database answered that the query needs a signed-in account. Since
 * October 2026 it says so for incident queries, "to mitigate a very high load of bot
 * traffic". This is not an outage, so the live sync stands down instead of failing
 * every day; the weekly public backup keeps the incident data current.
 */
final class AiidLoginRequired extends RuntimeException {}
