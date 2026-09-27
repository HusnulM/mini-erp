<?php

namespace Modules\Core\Services;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Core\Models\Setting;

/**
 * Settings service (TDD §9 "Tiga lapis konfigurasi").
 *
 * Definitions live in Modules/{Name}/config/settings.php:
 *   'key' => ['type' => 'bool|int|decimal|string|select', 'default' => ..., 'label' => ...,
 *             'rules' => [...], 'options' => [...] (select), 'help' => ...]
 *
 * Resolution: company row → tenant row (company_id NULL) → default. Resolved
 * values are cached per tenant (cache tags are tenant-scoped) + module +
 * company, and flushed when a value of that module is saved.
 */
class Settings
{
    /** @var array<string, array<string, array>> */
    private array $definitions = [];

    public function __construct(private readonly ModuleRegistry $registry) {}

    /** @return array<string, array> key => definition */
    public function definitions(string $module): array
    {
        if (! isset($this->definitions[$module])) {
            $file = $this->registry->get($module)->path.'/config/settings.php';
            $this->definitions[$module] = is_file($file) ? require $file : [];
        }

        return $this->definitions[$module];
    }

    /** "module.key" or (module, key). */
    public function get(string $module, ?string $key = null, ?int $companyId = null): mixed
    {
        if ($key === null) {
            [$module, $key] = explode('.', $module, 2);
        }

        $this->definition($module, $key);

        return $this->all($module, $companyId)[$key];
    }

    /** @return array<string, mixed> every key of the module, resolved */
    public function all(string $module, ?int $companyId = null): array
    {
        return Cache::tags(["settings:{$module}"])->remember(
            "settings:{$module}:".($companyId ?? 0),
            3600,
            function () use ($module, $companyId) {
                $rows = Setting::where('module', $module)
                    ->where(fn ($q) => $q->whereNull('company_id')->when($companyId, fn ($q) => $q->orWhere('company_id', $companyId)))
                    ->get()
                    ->sortBy(fn (Setting $s) => $s->company_id === null ? 0 : 1); // company rows win

                $definitions = $this->definitions($module);
                $values = [];

                foreach ($definitions as $key => $def) {
                    $values[$key] = $def['default'] ?? null;
                }
                foreach ($rows as $row) {
                    if (array_key_exists($row->key, $values)) {
                        $values[$row->key] = $row->value;
                    }
                }
                foreach ($values as $key => $value) {
                    $values[$key] = $this->cast($definitions[$key], $value);
                }

                return $values;
            }
        );
    }

    /**
     * Validates and stores values (company level, or tenant level when
     * $companyId is null).
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    public function set(string $module, array $values, ?int $companyId = null): void
    {
        $definitions = $this->definitions($module);
        $unknown = array_diff(array_keys($values), array_keys($definitions));

        if ($unknown) {
            throw new InvalidArgumentException("Unknown setting(s) for [{$module}]: ".implode(', ', $unknown).'.');
        }

        Validator::make($values, $this->rules($module, array_keys($values)), [], array_map(fn ($d) => $d['label'] ?? '', $definitions))->validate();

        foreach ($values as $key => $value) {
            $setting = Setting::firstOrNew(['company_id' => $companyId, 'module' => $module, 'key' => $key]);
            $setting->value = $this->cast($definitions[$key], $value);
            $setting->updated_by = auth('web')->id();
            $setting->save();
        }

        Cache::tags(["settings:{$module}"])->flush();
    }

    /**
     * @param  list<string>|null  $keys
     * @return array<string, array>
     */
    public function rules(string $module, ?array $keys = null): array
    {
        $rules = [];

        foreach ($this->definitions($module) as $key => $def) {
            if ($keys !== null && ! in_array($key, $keys, true)) {
                continue;
            }

            $rules[$key] = $def['rules'] ?? match ($def['type']) {
                'bool' => ['boolean'],
                'int' => ['integer'],
                'decimal' => ['numeric'],
                'select' => ['in:'.implode(',', array_keys($def['options'] ?? []))],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    private function definition(string $module, string $key): array
    {
        return $this->definitions($module)[$key]
            ?? throw new InvalidArgumentException("Unknown setting [{$module}.{$key}].");
    }

    private function cast(array $def, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($def['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            'decimal' => (float) $value,
            default => (string) $value,
        };
    }
}
