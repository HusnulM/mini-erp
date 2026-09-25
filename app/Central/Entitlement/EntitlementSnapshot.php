<?php

namespace App\Central\Entitlement;

use App\Support\Modules\ModuleState;
use App\Support\Modules\PlanLimits;
use Carbon\CarbonImmutable;

/**
 * What one tenant may use, computed from central tables. Cached as a plain
 * array (toArray/fromArray): the cache refuses to unserialize objects
 * (config cache.serializable_classes).
 */
final readonly class EntitlementSnapshot
{
    /**
     * @param  array<string, ModuleState>  $states  module code => state
     * @param  list<string>  $entitled  modules the tenant may activate
     * @param  array<string, ?string>  $expiresAt  module code => ISO-8601 date
     */
    public function __construct(
        public array $states,
        public array $entitled,
        public array $expiresAt,
        public PlanLimits $limits,
    ) {}

    /** @return array{states: array<string, string>, entitled: list<string>, expires_at: array<string, ?string>, limits: array<string, ?int>} */
    public function toArray(): array
    {
        return [
            'states' => array_map(fn (ModuleState $s) => $s->value, $this->states),
            'entitled' => $this->entitled,
            'expires_at' => $this->expiresAt,
            'limits' => ['users' => $this->limits->users, 'companies' => $this->limits->companies, 'stores' => $this->limits->stores],
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            states: array_map(fn (string $s) => ModuleState::from($s), $data['states']),
            entitled: $data['entitled'],
            expiresAt: $data['expires_at'],
            limits: new PlanLimits(...$data['limits']),
        );
    }

    public function state(string $module): ModuleState
    {
        return $this->states[$module] ?? ModuleState::Inactive;
    }

    public function isEntitled(string $module): bool
    {
        return in_array($module, $this->entitled, true);
    }

    public function expiresAt(string $module): ?CarbonImmutable
    {
        $date = $this->expiresAt[$module] ?? null;

        return $date ? CarbonImmutable::parse($date) : null;
    }
}
