<?php

namespace App\Tenancy\Middleware;

use App\Contracts\ModuleEntitlement;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `module:{code}`, added to every module's tenant routes by
 * ModuleServiceProvider (TDD §3 request lifecycle).
 *
 *  - inactive → 403
 *  - readonly → GET/HEAD/OPTIONS pass, writes get 403 with a clear message
 *               (subscription ended; data stays readable, ADR-06)
 */
class EnsureModuleIsActive
{
    public function __construct(
        private readonly ModuleEntitlement $entitlement,
        private readonly ModuleRegistry $registry,
    ) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $state = $this->entitlement->state($module);

        if ($state === ModuleState::Active || ($state === ModuleState::ReadOnly && $request->isMethodSafe())) {
            return $next($request);
        }

        $name = $this->registry->has($module) ? $this->registry->get($module)->name : $module;
        $message = $state === ModuleState::ReadOnly
            ? "Modul {$name} dalam mode baca saja karena langganan sudah berakhir. Data tetap bisa dilihat dan diekspor, tetapi tidak bisa diubah. Perpanjang langganan untuk mengaktifkannya kembali."
            : "Modul {$name} tidak aktif untuk akun Anda.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'module' => $module, 'state' => $state->value], 403);
        }

        return response()->view('tenancy.module-blocked', ['message' => $message, 'readonly' => $state === ModuleState::ReadOnly], 403);
    }
}
