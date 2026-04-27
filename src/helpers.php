<?php

if (!function_exists('stimata')) {
    /**
     * Get the Stimata client instance or call a method directly.
     *
     * @param  string|null  $method
     * @param  array  $parameters
     * @return \Stimata\Portal\StimataClient|mixed
     */
    function stimata($method = null, ...$parameters)
    {
        $client = app('stimata');

        if ($method === null) {
            return $client;
        }

        return $client->$method(...$parameters);
    }
}

if (!function_exists('stimata_token')) {
    /**
     * Get the current access token from session.
     *
     * @return string|null
     */
    function stimata_token()
    {
        $tokenKey = config('stimata.middleware.session_token_key', 'stimata_access_token');
        return session($tokenKey);
    }
}

if (!function_exists('stimata_refresh_token')) {
    /**
     * Get the current refresh token from session.
     *
     * @return string|null
     */
    function stimata_refresh_token()
    {
        $refreshKey = config('stimata.middleware.session_refresh_key', 'stimata_refresh_token');
        return session($refreshKey);
    }
}

if (!function_exists('stimata_user')) {
    /**
     * Get the authenticated user from Stimata.
     *
     * @return array|null
     */
    function stimata_user()
    {
        $token = stimata_token();

        if (!$token) {
            return null;
        }

        try {
            return stimata('getUser', $token);
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('stimata_check_access')) {
    /**
     * Check if the current user has access to a resource.
     *
     * @param  string  $resource
     * @return bool
     */
    function stimata_check_access($resource)
    {
        $token = stimata_token();

        if (!$token) {
            return false;
        }

        try {
            return stimata('checkAccess', $token, $resource);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('stimata_logout')) {
    /**
     * Logout and revoke the current refresh token.
     *
     * @return bool
     */
    function stimata_logout()
    {
        $refreshToken = stimata_refresh_token();

        if ($refreshToken) {
            try {
                stimata('revoke', $refreshToken);
            } catch (Exception $e) {
                // Ignore revoke errors
            }
        }

        // Clear session
        $config = config('stimata.middleware', []);
        session()->forget([
            $config['session_token_key'] ?? 'stimata_access_token',
            $config['session_refresh_key'] ?? 'stimata_refresh_token',
            $config['session_expires_key'] ?? 'stimata_token_expires_at',
        ]);

        return true;
    }
}

if (!function_exists('stimata_is_authenticated')) {
    /**
     * Check if the user is currently authenticated.
     *
     * @return bool
     */
    function stimata_is_authenticated()
    {
        $token = stimata_token();

        if (!$token) {
            return false;
        }

        try {
            $introspection = stimata('introspect', $token);
            return isset($introspection['active']) && $introspection['active'];
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('stimata_auth_url')) {
    /**
     * Generate an authentication URL and store state in session.
     *
     * @return string
     */
    function stimata_auth_url()
    {
        $authData = stimata('getAuthUrl');
        session(['oauth_state' => $authData['state']]);
        return $authData['url'];
    }
}

if (!function_exists('stimata_handle_callback')) {
    /**
     * Handle the OAuth callback and store tokens in session.
     *
     * @param  string|null  $currentUrl
     * @return array Token data
     * @throws Exception
     */
    function stimata_handle_callback($currentUrl = null)
    {
        if ($currentUrl === null) {
            $currentUrl = request()->fullUrl();
        }

        $expectedState = session('oauth_state');

        if (!$expectedState) {
            throw new Exception('No state found in session');
        }

        $tokenData = stimata('handleCallback', $currentUrl, $expectedState);

        // Store tokens in session
        $config = config('stimata.middleware', []);
        session([
            $config['session_token_key'] ?? 'stimata_access_token' => $tokenData['access_token'],
            $config['session_refresh_key'] ?? 'stimata_refresh_token' => $tokenData['refresh_token'] ?? null,
            $config['session_expires_key'] ?? 'stimata_token_expires_at' => time() + ($tokenData['expires_in'] ?? 3600),
        ]);

        // Clear the state
        session()->forget('oauth_state');

        return $tokenData;
    }
}

if (!function_exists('stimata_client_credentials')) {
    /**
     * Get an access token using client credentials and optionally store in session.
     *
     * @param  string|array|null  $scope
     * @param  bool  $storeInSession
     * @return array Token data
     */
    function stimata_client_credentials($scope = null, $storeInSession = false)
    {
        $tokenData = stimata('getTokenWithClientCredentials', $scope);

        if ($storeInSession) {
            $config = config('stimata.middleware', []);
            session([
                $config['session_token_key'] ?? 'stimata_access_token' => $tokenData['access_token'],
                $config['session_expires_key'] ?? 'stimata_token_expires_at' => time() + ($tokenData['expires_in'] ?? 3600),
            ]);
        }

        return $tokenData;
    }
}

if (!function_exists('stimata_switch_role')) {
    /**
     * Switch to a different role and update session tokens.
     *
     * @param  string  $role
     * @return array Token data
     * @throws Exception
     */
    function stimata_switch_role($role)
    {
        $token = stimata_token();

        if (!$token) {
            throw new Exception('No access token found');
        }

        $tokenData = stimata('switchRole', $token, $role);

        // Update session with new token
        $config = config('stimata.middleware', []);
        session([
            $config['session_token_key'] ?? 'stimata_access_token' => $tokenData['access_token'],
            $config['session_expires_key'] ?? 'stimata_token_expires_at' => time() + ($tokenData['expires_in'] ?? 3600),
        ]);

        if (isset($tokenData['refresh_token'])) {
            session([
                $config['session_refresh_key'] ?? 'stimata_refresh_token' => $tokenData['refresh_token'],
            ]);
        }

        return $tokenData;
    }
}
