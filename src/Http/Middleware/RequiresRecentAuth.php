<?php

declare(strict_types=1);

namespace Alma\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequiresRecentAuth
{
    public function handle(Request $request, Closure $next, string $minutes = ''): Response
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }

        $token = $user->currentAccessToken();
        if ($token === null) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }

        $window = (int) ($minutes !== '' ? $minutes : config('alma-auth.step_up_minutes', 10));
        $key = 'alma_auth_step_up:'.$token->getKey();
        $confirmedAt = cache()->get($key);

        if ($confirmedAt === null || now()->timestamp - (int) $confirmedAt > $window * 60) {
            return response()->json(['status' => 'step_up_required'], 403);
        }

        return $next($request);
    }
}
