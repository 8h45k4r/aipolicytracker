<?php

namespace App\Services\PolicyData;

/**
 * Small JSON Schema (draft 2020-12 subset) validator used by policy:validate.
 *
 * Supported keywords: type, properties, required, items, minItems, maxItems, enum, pattern,
 * minLength, maxLength, minimum, maximum, format (date, uri), anyOf, allOf, $ref (local "#/$defs/x"
 * and sibling-file "name.schema.json#/$defs/x"), additionalProperties (schema form).
 * It is deliberately dependency-free so contributors can run it anywhere PHP runs.
 */
class SchemaValidator
{
    /** @var array<string, array> */
    private array $schemas = [];

    public function __construct(private readonly string $schemaDir) {}

    public function loadSchema(string $file): array
    {
        if (! isset($this->schemas[$file])) {
            $path = rtrim($this->schemaDir, '/').'/'.$file;
            if (! is_file($path)) {
                throw new \RuntimeException("Schema not found: {$path}");
            }
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->schemas[$file] = $decoded;
        }

        return $this->schemas[$file];
    }

    /**
     * @return list<string> error messages (empty when valid)
     */
    public function validate(mixed $data, string $schemaFile): array
    {
        $schema = $this->loadSchema($schemaFile);
        $errors = [];
        $this->check($data, $schema, $schemaFile, '$', $errors);

        return $errors;
    }

    private function resolveRef(string $ref, string $currentFile): array
    {
        [$file, $pointer] = str_contains($ref, '#') ? explode('#', $ref, 2) : [$ref, ''];
        $file = $file === '' ? $currentFile : $file;
        $schema = $this->loadSchema($file);
        $node = $schema;
        foreach (array_filter(explode('/', ltrim($pointer, '/'))) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                throw new \RuntimeException("Unresolvable \$ref {$ref}");
            }
            $node = $node[$segment];
        }
        $node['__file'] = $file;

        return $node;
    }

    private function check(mixed $data, array $schema, string $file, string $path, array &$errors): void
    {
        if (isset($schema['$ref'])) {
            $resolved = $this->resolveRef($schema['$ref'], $file);
            $refFile = $resolved['__file'];
            unset($resolved['__file']);
            $this->check($data, $resolved, $refFile, $path, $errors);

            return;
        }

        if (isset($schema['allOf'])) {
            foreach ($schema['allOf'] as $sub) {
                $this->check($data, $sub, $file, $path, $errors);
            }
        }

        if (isset($schema['anyOf'])) {
            $matched = false;
            $collected = [];
            foreach ($schema['anyOf'] as $sub) {
                $subErrors = [];
                $this->check($data, $sub, $file, $path, $subErrors);
                if ($subErrors === []) {
                    $matched = true;
                    break;
                }
                $collected[] = $subErrors;
            }
            if (! $matched) {
                $errors[] = "{$path}: value does not match any allowed form (".implode(' | ', array_map(fn ($e) => implode('; ', $e), $collected)).')';
            }
        }

        if (isset($schema['type']) && ! $this->matchesType($data, $schema['type'])) {
            $errors[] = "{$path}: expected type {$schema['type']}, got ".get_debug_type($data);

            return;
        }

        if (isset($schema['enum']) && ! in_array($data, $schema['enum'], true)) {
            $errors[] = "{$path}: value ".json_encode($data).' is not one of ['.implode(', ', array_map('strval', $schema['enum'])).']';
        }

        if (is_string($data)) {
            if (isset($schema['minLength']) && mb_strlen($data) < $schema['minLength']) {
                $errors[] = "{$path}: must be at least {$schema['minLength']} characters";
            }
            if (isset($schema['maxLength']) && mb_strlen($data) > $schema['maxLength']) {
                $errors[] = "{$path}: must be at most {$schema['maxLength']} characters";
            }
            if (isset($schema['pattern']) && ! preg_match('/'.str_replace('/', '\/', $schema['pattern']).'/u', $data)) {
                $errors[] = "{$path}: does not match pattern {$schema['pattern']}";
            }
            if (($schema['format'] ?? null) === 'date' && ! $this->isDate($data)) {
                $errors[] = "{$path}: must be an ISO date (YYYY-MM-DD)";
            }
            if (($schema['format'] ?? null) === 'uri' && filter_var($data, FILTER_VALIDATE_URL) === false) {
                $errors[] = "{$path}: must be an absolute URL";
            }
        }

        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "{$path}: must be >= {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "{$path}: must be <= {$schema['maximum']}";
            }
        }

        if (is_array($data) && ($this->isList($data) || $data === []) && ($schema['type'] ?? null) === 'array') {
            // An empty YAML list parses as [], which is indistinguishable from an empty
            // mapping, so the array bounds are checked here where the schema says which it is.
            $count = count($data);
            if (isset($schema['minItems']) && $count < $schema['minItems']) {
                $errors[] = "{$path}: must have at least {$schema['minItems']} item(s)";
            }
            if (isset($schema['maxItems']) && $count > $schema['maxItems']) {
                $errors[] = "{$path}: must have at most {$schema['maxItems']} item(s)";
            }
        }

        if (is_array($data) && $this->isList($data) && isset($schema['items'])) {
            foreach ($data as $i => $item) {
                $this->check($item, $schema['items'], $file, "{$path}[{$i}]", $errors);
            }
        }

        if (is_array($data) && ! $this->isList($data) || ($data === [] && ($schema['type'] ?? null) === 'object')) {
            foreach ($schema['required'] ?? [] as $required) {
                if (! array_key_exists($required, $data)) {
                    $errors[] = "{$path}: missing required field \"{$required}\"";
                }
            }
            foreach ($schema['properties'] ?? [] as $key => $propSchema) {
                if (array_key_exists($key, $data)) {
                    $this->check($data[$key], $propSchema, $file, "{$path}.{$key}", $errors);
                }
            }
            if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
                foreach ($data as $key => $value) {
                    if (! isset($schema['properties'][$key])) {
                        $this->check($value, $schema['additionalProperties'], $file, "{$path}.{$key}", $errors);
                    }
                }
            }
        }
    }

    private function matchesType(mixed $data, string $type): bool
    {
        return match ($type) {
            'string' => is_string($data),
            'integer' => is_int($data),
            'number' => is_int($data) || is_float($data),
            'boolean' => is_bool($data),
            'null' => $data === null,
            'array' => is_array($data) && $this->isList($data),
            'object' => is_array($data) && ($data === [] || ! $this->isList($data)),
            default => true,
        };
    }

    private function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    private function isDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }
}
