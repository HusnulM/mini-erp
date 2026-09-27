<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only audit trail (PRD §49). Rows cannot be updated or deleted
 * through the application.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function record(Model|string $subject, string $event, array $old = [], array $new = []): self
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return static::create([
            'user_id' => auth('web')->id(),
            'event' => $event,
            'auditable_type' => $subject instanceof Model ? $subject->getMorphClass() : $subject,
            'auditable_id' => $subject instanceof Model ? $subject->getKey() : null,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
