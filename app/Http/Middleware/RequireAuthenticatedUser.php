<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RequireAuthenticatedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user && $request->hasSession()) {
            $sessionUserId = $request->session()->get('authenticated_user_id');
            if ($sessionUserId) {
                $user = User::find($sessionUserId);
                if ($user) {
                    Auth::setUser($user);
                    $request->setUserResolver(static fn () => $user);
                }
            }
        }

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'unauthorized',
                        'message' => 'Authentication required.',
                    ],
                ], 401);
            }

            return redirect('/signin');
        }

        return $next($request);
    }
}
