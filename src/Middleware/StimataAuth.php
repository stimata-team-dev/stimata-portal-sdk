<?php

namespace Stimata\Portal\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stimata\Portal\StimataClient;
use Illuminate\Support\Facades\Log;

/**
 * StimataAuth Middleware
 *
 * Validates the access token from session and automatically refreshes
 * expired tokens if a refresh token is available.
 */
class StimataAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        $config = config('stimata');
        $tokenKey = $config['middleware']['session_token_key'] ?? 'stimata_access_token';
        $refreshKey = $config['middleware']['session_refresh_key'] ?? 'stimata_refresh_token';
        $expiresKey = $config['middleware']['session_expires_key'] ?? 'stimata_token_expires_at';
        $redirectRoute = $config['middleware']['redirect_on_failure'] ?? '/login';

        $accessToken = $request->session()->get($tokenKey);
        $refreshToken = $request->session()->get($refreshKey);
        $expiresAt = $request->session()->get($expiresKey, 0);

        // No access token at all
        if (!$accessToken) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'No access token found'
                ], 401);
            }

            return redirect($redirectRoute)->with('error', 'Please login to continue');
        }

        try {
            $client = app('stimata');
            $autoRefresh = $config['auto_refresh'] ?? true;
            $refreshBuffer = $config['refresh_buffer'] ?? 300;

            // Check if token is about to expire or already expired
            $needsRefresh = time() >= ($expiresAt - $refreshBuffer);

            if ($needsRefresh && $autoRefresh && $refreshToken) {
                // Try to refresh the token
                try {
                    $tokenData = $client->refreshToken($refreshToken);

                    // Update session with new tokens
                    $request->session()->put($tokenKey, $tokenData['access_token']);
                    $request->session()->put($expiresKey, time() + $tokenData['expires_in']);

                    if (isset($tokenData['refresh_token'])) {
                        $request->session()->put($refreshKey, $tokenData['refresh_token']);
                    }

                    if ($config['logging']['enabled'] ?? false) {
                        Log::channel($config['logging']['channel'] ?? 'stack')
                            ->info('STIMATA: Token refreshed successfully');
                    }

                    $accessToken = $tokenData['access_token'];
                } catch (\Exception $e) {
                    // Refresh failed, clear session and redirect to login
                    if ($config['logging']['enabled'] ?? false) {
                        Log::channel($config['logging']['channel'] ?? 'stack')
                            ->error('STIMATA: Token refresh failed', [
                                'error' => $e->getMessage()
                            ]);
                    }

                    $request->session()->forget([$tokenKey, $refreshKey, $expiresKey]);

                    if ($request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Unauthorized',
                            'message' => 'Token refresh failed'
                        ], 401);
                    }

                    return redirect($redirectRoute)->with('error', 'Your session has expired. Please login again.');
                }
            } else {
                // Validate current token via introspection
                $introspection = $client->introspect($accessToken);

                if (!isset($introspection['active']) || !$introspection['active']) {
                    // Token is not active
                    if ($refreshToken && $autoRefresh) {
                        // Try to refresh
                        try {
                            $tokenData = $client->refreshToken($refreshToken);

                            $request->session()->put($tokenKey, $tokenData['access_token']);
                            $request->session()->put($expiresKey, time() + $tokenData['expires_in']);

                            if (isset($tokenData['refresh_token'])) {
                                $request->session()->put($refreshKey, $tokenData['refresh_token']);
                            }

                            $accessToken = $tokenData['access_token'];
                        } catch (\Exception $e) {
                            // Refresh failed
                            $request->session()->forget([$tokenKey, $refreshKey, $expiresKey]);

                            if ($request->expectsJson()) {
                                return response()->json([
                                    'success' => false,
                                    'error' => 'Unauthorized',
                                    'message' => 'Invalid or expired token'
                                ], 401);
                            }

                            return redirect($redirectRoute)->with('error', 'Your session has expired. Please login again.');
                        }
                    } else {
                        // No refresh token or auto-refresh disabled
                        $request->session()->forget([$tokenKey, $refreshKey, $expiresKey]);

                        if ($request->expectsJson()) {
                            return response()->json([
                                'success' => false,
                                'error' => 'Unauthorized',
                                'message' => 'Invalid or expired token'
                            ], 401);
                        }

                        return redirect($redirectRoute)->with('error', 'Your session has expired. Please login again.');
                    }
                }
            }

            // Store the valid access token in request for easy access in controllers
            $request->attributes->set('stimata_access_token', $accessToken);

        } catch (\Exception $e) {
            if ($config['logging']['enabled'] ?? false) {
                Log::channel($config['logging']['channel'] ?? 'stack')
                    ->error('STIMATA: Authentication error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication Error',
                    'message' => 'Failed to authenticate request'
                ], 500);
            }

            return redirect($redirectRoute)->with('error', 'Authentication error occurred');
        }

        return $next($request);
    }
}
