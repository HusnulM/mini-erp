<?php

namespace App\Support\Modules;

use InvalidArgumentException;

/**
 * Parsed Modules/{Name}/module.json.
 *
 * One file serves two readers: nwidart/laravel-modules uses name, alias,
 * priority, providers and files; the ERP reads everything else (TDD §6).
 */
final readonly class ModuleManifest
{
    /**
     * @param  list<string>  $requires  hard dependencies (module codes)
     * @param  list<string>  $optional  soft integrations, used when present
     * @param  list<string>  $permissions
     * @param  list<string>  $documentTypes
     * @param  list<string>  $emits
     * @param  list<string>  $listens
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $folder,
        public string $path,
        public string $version,
        public string $description = '',
        public bool $isCore = false,
        public bool $isAddon = false,
        public string $priceMonthly = '0',
        public string $status = 'available',
        public array $requires = [],
        public array $optional = [],
        public array $permissions = [],
        public array $documentTypes = [],
        public array $emits = [],
        public array $listens = [],
        public ?string $installer = null,
        public array $raw = [],
    ) {}

    public static function fromFile(string $file): self
    {
        $raw = json_decode((string) file_get_contents($file), true);

        if (! is_array($raw)) {
            throw new InvalidArgumentException("Invalid JSON in [{$file}].");
        }

        $path = dirname($file);
        $code = $raw['code'] ?? null;

        if (! is_string($code) || ! preg_match('/^[a-z][a-z0-9_]{1,39}$/', $code)) {
            throw new InvalidArgumentException("[{$file}] needs a lowercase \"code\" (a-z, 0-9, _).");
        }

        if (($raw['alias'] ?? $code) !== $code) {
            throw new InvalidArgumentException("[{$file}] \"alias\" must equal \"code\" ({$code}).");
        }

        $version = (string) ($raw['version'] ?? '');
        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException("[{$file}] needs a semantic \"version\" (x.y.z).");
        }

        $status = $raw['status'] ?? 'available';
        if (! in_array($status, ['available', 'beta', 'deprecated'], true)) {
            throw new InvalidArgumentException("[{$file}] has invalid status [{$status}].");
        }

        return new self(
            code: $code,
            name: (string) ($raw['display_name'] ?? $raw['name'] ?? $code),
            folder: basename($path),
            path: $path,
            version: $version,
            description: (string) ($raw['description'] ?? ''),
            isCore: (bool) ($raw['is_core'] ?? false),
            isAddon: (bool) ($raw['is_addon'] ?? false),
            priceMonthly: (string) ($raw['price_monthly'] ?? '0'),
            status: $status,
            requires: self::codes($raw['requires'] ?? [], $file, 'requires'),
            optional: self::codes($raw['optional'] ?? [], $file, 'optional'),
            permissions: array_values($raw['permissions'] ?? []),
            documentTypes: array_values($raw['document_types'] ?? []),
            emits: array_values($raw['emits'] ?? []),
            listens: array_values($raw['listens'] ?? []),
            installer: $raw['installer'] ?? null,
            raw: $raw,
        );
    }

    /** Directory with this module's tenant-database migrations. */
    public function tenantMigrationPath(): string
    {
        return $this->path.'/database/migrations/tenant';
    }

    /** @return list<string> */
    private static function codes(mixed $value, string $file, string $key): array
    {
        if (! is_array($value) || array_filter($value, fn ($v) => ! is_string($v))) {
            throw new InvalidArgumentException("[{$file}] \"{$key}\" must be a list of module codes.");
        }

        return array_values(array_unique($value));
    }
}
