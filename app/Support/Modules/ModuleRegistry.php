<?php

namespace App\Support\Modules;

use InvalidArgumentException;

/**
 * Reads every Modules/{Name}/module.json and answers dependency questions.
 * The code on disk is the source of truth; the central `modules` table is a
 * synced copy (`php artisan modules:sync`).
 */
class ModuleRegistry
{
    /** @var array<string, ModuleManifest>|null */
    private ?array $manifests = null;

    public function __construct(private readonly string $modulesPath) {}

    /** @return array<string, ModuleManifest> keyed by code, in install order */
    public function all(): array
    {
        if ($this->manifests === null) {
            $found = [];

            foreach (glob($this->modulesPath.'/*/module.json') ?: [] as $file) {
                $manifest = ModuleManifest::fromFile($file);

                if (isset($found[$manifest->code])) {
                    throw new InvalidArgumentException(
                        "Module code [{$manifest->code}] is used by both {$found[$manifest->code]->folder} and {$manifest->folder}."
                    );
                }

                $found[$manifest->code] = $manifest;
            }

            $this->validate($found);
            $this->manifests = $this->sortTopologically($found);
        }

        return $this->manifests;
    }

    public function has(string $code): bool
    {
        return isset($this->all()[$code]);
    }

    public function get(string $code): ModuleManifest
    {
        return $this->all()[$code] ?? throw new InvalidArgumentException("Unknown module [{$code}].");
    }

    /** @return array<string, ModuleManifest> */
    public function core(): array
    {
        return array_filter($this->all(), fn (ModuleManifest $m) => $m->isCore);
    }

    /**
     * Hard dependencies of $code (transitive), in install order, excluding $code.
     *
     * @return list<string>
     */
    public function dependenciesOf(string $code): array
    {
        $needed = [];
        $walk = function (string $c) use (&$walk, &$needed) {
            foreach ($this->get($c)->requires as $dep) {
                if (! isset($needed[$dep])) {
                    $needed[$dep] = true;
                    $walk($dep);
                }
            }
        };
        $walk($code);

        return array_values(array_filter(array_keys($this->all()), fn ($c) => isset($needed[$c])));
    }

    /**
     * Modules that (transitively) require $code. A module cannot be
     * deactivated while any of these is active.
     *
     * @return list<string>
     */
    public function dependentsOf(string $code): array
    {
        $this->get($code);

        return array_values(array_filter(
            array_keys($this->all()),
            fn ($c) => $c !== $code && in_array($code, $this->dependenciesOf($c), true)
        ));
    }

    /**
     * $codes plus everything they require, in install order.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function withDependencies(array $codes): array
    {
        $set = [];
        foreach ($codes as $code) {
            $set[$code] = true;
            foreach ($this->dependenciesOf($code) as $dep) {
                $set[$dep] = true;
            }
        }

        return array_values(array_filter(array_keys($this->all()), fn ($c) => isset($set[$c])));
    }

    /** @param  array<string, ModuleManifest>  $manifests */
    private function validate(array $manifests): void
    {
        foreach ($manifests as $m) {
            foreach ([...$m->requires, ...$m->optional] as $dep) {
                if (! isset($manifests[$dep])) {
                    throw new InvalidArgumentException("Module [{$m->code}] depends on unknown module [{$dep}].");
                }
                if ($dep === $m->code) {
                    throw new InvalidArgumentException("Module [{$m->code}] cannot depend on itself.");
                }
            }

            if ($m->isCore) {
                foreach ($m->requires as $dep) {
                    if (! $manifests[$dep]->isCore) {
                        throw new InvalidArgumentException("Core module [{$m->code}] cannot require non-core module [{$dep}].");
                    }
                }
            }
        }
    }

    /**
     * Kahn's algorithm on hard dependencies; ties broken by (core first, code).
     *
     * @param  array<string, ModuleManifest>  $manifests
     * @return array<string, ModuleManifest>
     */
    private function sortTopologically(array $manifests): array
    {
        $pending = $manifests;
        $sorted = [];

        while ($pending) {
            $ready = array_filter($pending, fn (ModuleManifest $m) => ! array_diff($m->requires, array_keys($sorted)));

            if (! $ready) {
                throw new InvalidArgumentException('Circular module dependency among: '.implode(', ', array_keys($pending)).'.');
            }

            uasort($ready, fn (ModuleManifest $a, ModuleManifest $b) => [! $a->isCore, $a->code] <=> [! $b->isCore, $b->code]);
            $next = array_key_first($ready);
            $sorted[$next] = $pending[$next];
            unset($pending[$next]);
        }

        return $sorted;
    }
}
