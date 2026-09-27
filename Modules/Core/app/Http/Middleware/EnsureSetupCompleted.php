<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `setup` middleware (TDD §9): until the required setup-wizard steps are
 * done (tenants.setup_completed_at), signed-in users are sent to the
 * wizard. Guests pass through, so `auth` can send them to the login page.
 */
class EnsureSetupCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (! $user || tenant()?->setup_completed_at !== null) {
            return $next($request);
        }

        if ($user->can('core.settings.manage')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Setup awal belum selesai.'], 409)
                : redirect()->route('core.setup.index');
        }

        return response()->view('core::setup.pending', [], 403);
    }
}
