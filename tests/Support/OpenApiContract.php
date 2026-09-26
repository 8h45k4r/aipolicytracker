<?php

namespace Tests\Support;

/**
 * Checks an API response against the schema this site publishes at
 * /openapi.json, in both directions: nothing the schema requires is missing,
 * and nothing the response carries is undocumented.
 *
 * Deliberately small — types (including nullable unions), required fields,
 * nested objects, arrays, $ref, and the date and uri formats — because that is
 * all the published document uses. A dependency would validate more of the
 * specification than this site has, and hide what is actually being checked.
 */
trait OpenApiContract
{
    /** @var array<string,mixed>|null */
    private ?array $openApiSpec = null;

    /**
     * @param  array<string,mixed>  $payload  the decoded response body
     */
    protected function assertMatchesOpenApi(string $path, array $payload, string $method = 'get', string $status = '200'): void
    {
        $this->openApiSpec ??= $this->get('/openapi.json')->assertOk()->json();
        $schema = $this->openApiSpec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'] ?? null;
        $this->assertNotNull($schema, "{$method} {$path} {$status} has no response schema in /openapi.json");
        $this->checkAgainst($schema, $payload, "{$path}");
    }

    private function checkAgainst(array $schema, mixed $value, string $at): void
    {
        if (isset($schema['$ref'])) {
            $name = substr($schema['$ref'], strlen('#/components/schemas/'));
            $this->assertArrayHasKey($name, $this->openApiSpec['components']['schemas'], "{$at}: unknown \$ref {$schema['$ref']}");
            $this->checkAgainst($this->openApiSpec['components']['schemas'][$name], $value, "{$at}<{$name}>");

            return;
        }

        $types = (array) ($schema['type'] ?? []);
        if ($types !== []) {
            $this->assertTrue($this->hasType($value, $types), "{$at}: expected ".implode('|', $types).', got '.get_debug_type($value));
        }
        if ($value === null) {
            return;
        }

        if (($schema['format'] ?? null) === 'date' && is_string($value)) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $value, "{$at}: not a date");
        }
        if (($schema['format'] ?? null) === 'uri' && is_string($value)) {
            $this->assertNotFalse(filter_var($value, FILTER_VALIDATE_URL), "{$at}: not a URI");
        }

        if (is_array($value) && array_is_list($value) && isset($schema['items'])) {
            foreach ($value as $i => $item) {
                $this->checkAgainst($schema['items'], $item, "{$at}[{$i}]");
            }
        }

        if (is_array($value) && isset($schema['properties']) && ! array_is_list($value)) {
            foreach ($schema['required'] ?? [] as $key) {
                $this->assertArrayHasKey($key, $value, "{$at}: required \"{$key}\" missing");
            }
            foreach ($value as $key => $v) {
                $this->assertArrayHasKey($key, $schema['properties'], "{$at}: \"{$key}\" is returned but not documented");
                $this->checkAgainst($schema['properties'][$key], $v, "{$at}.{$key}");
            }
        }
    }

    private function hasType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            $ok = match ($type) {
                'null' => $value === null,
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
                default => false,
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }
}
