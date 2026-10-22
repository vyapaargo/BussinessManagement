<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;

class CheckSubscriptionValidity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Allow unauthenticated requests or Super Admin
        if (!$user || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        $orgId = $user->organization_id;

        if (!$orgId) {
            return $next($request);
        }

        // Allow logout
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        // Verify organization active status
        $org = $user->organization;
        if ($org && !$org->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your organization account has been deactivated by the administrator. Please contact support.',
            ]);
        }

        // Allow subscription page and profile routes
        if ($request->routeIs('organization.subscription.*') || $request->routeIs('profile.*') || $request->routeIs('organization.profile*')) {
            return $next($request);
        }

        // Check if subscription or trial is expired
        if (SubscriptionService::isExpired($orgId)) {
            return redirect()->route('organization.subscription.index')->with('error', 'Your free trial or subscription has expired. Please select a plan to unlock full access to your account.');
        }

        return $next($request);
    }
}
