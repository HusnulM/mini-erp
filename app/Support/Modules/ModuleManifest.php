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
     * @param  list<array{label: string, route: string, permission?: ?string, order?: int}>  $menu
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
        public array $menu = [],
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
            menu: self::menu($raw['menu'] ?? [], $file),
            raw: $raw,
        );
    }

    /** Directory with this module's tenant-database migrations. */
    public function tenantMigrationPath(): string
    {
        return $this->path.'/database/migrations/tenant';
    }

    /**
     * Permission names with "entity.*" expanded to the standard actions,
     * e.g. core.company.* → core.company.view, core.company.create, ...
     *
     * @param  list<string>  $actions
     * @return list<string>
     */
    public function expandedPermissions(array $actions): array
    {
        $names = [];

        foreach ($this->permissions as $permission) {
            if (str_ends_with($permission, '.*')) {
                foreach ($actions as $action) {
                    $names[] = substr($permission, 0, -1).$action;
                }
            } else {
                $names[] = $permission;
            }
        }

        return array_values(array_unique($names));
    }

    /** @return list<array{label: string, route: string, permission: ?string, order: int}> */
    private static function menu(mixed $value, string $file): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("[{$file}] \"menu\" must be a list.");
        }

        return array_map(function ($item) use ($file) {
            if (! is_array($item) || ! is_string($item['label'] ?? null) || ! is_string($item['route'] ?? null)) {
                throw new InvalidArgumentException("[{$file}] every \"menu\" item needs a \"label\" and a \"route\".");
            }

            return [
                'label' => $item['label'],
                'route' => $item['route'],
                'permission' => $item['permission'] ?? null,
                'order' => (int) ($item['order'] ?? 100),
            ];
        }, array_values($value));
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
