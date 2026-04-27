<?php

namespace Stimata\Portal\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stimata\Portal\StimataClient;
use Illuminate\Support\Facades\Log;

/**
 * StimataCheckAccess Middleware
 *
 * Validates that the authenticated user has access to specific resources.
 * Usage: Route::get('/admin', ...)->middleware('stimata.access:users.write');
 */
class StimataCheckAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$resources
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$resources)
    {
        $config = config('stimata');
        $tokenKey = $config['middleware']['session_token_key'] ?? 'stimata_access_token';

        // Get access token from session or request attribute (set by StimataAuth middleware)
        $accessToken = $request->attributes->get('stimata_access_token')
                       ?? $request->session()->get($tokenKey);

        if (!$accessToken) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'No access token found'
                ], 401);
            }

            return redirect($config['middleware']['redirect_on_failure'] ?? '/login')
                ->with('error', 'Please login to continue');
        }

        // If no resources specified, just check if token is valid
        if (empty($resources)) {
            return $next($request);
        }

        try {
            $client = app('stimata');
            $deniedResources = [];

            // Check access for each resource
            foreach ($resources as $resource) {
                try {
                    $hasAccess = $client->checkAccess($accessToken, $resource);

                    if (!$hasAccess) {
                        $deniedResources[] = $resource;
                    }
                } catch (\Exception $e) {
                    if ($config['logging']['enabled'] ?? false) {
                        Log::channel($config['logging']['channel'] ?? 'stack')
                            ->error('STIMATA: Access check failed for resource', [
                                'resource' => $resource,
                                'error' => $e->getMessage()
                            ]);
                    }

                    $deniedResources[] = $resource;
                }
            }

            // If any resource is denied, return forbidden
            if (!empty($deniedResources)) {
                if ($config['logging']['enabled'] ?? false) {
                    Log::channel($config['logging']['channel'] ?? 'stack')
                        ->warning('STIMATA: Access denied to resources', [
                            'resources' => $deniedResources,
                            'user_ip' => $request->ip()
                        ]);
                }

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Forbidden',
                        'message' => 'You do not have permission to access this resource',
                        'denied_resources' => $deniedResources
                    ], 403);
                }

                abort(403, 'You do not have permission to access this resource.');
            }

            // Store checked resources in request for reference
            $request->attributes->set('stimata_checked_resources', $resources);

        } catch (\Exception $e) {
            if ($config['logging']['enabled'] ?? false) {
                Log::channel($config['logging']['channel'] ?? 'stack')
                    ->error('STIMATA: Access check error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authorization Error',
                    'message' => 'Failed to verify access permissions'
                ], 500);
            }

            abort(500, 'Failed to verify access permissions');
        }

        return $next($request);
    }
}
