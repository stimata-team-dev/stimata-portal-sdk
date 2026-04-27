<?php

namespace Stimata\Portal\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getAuthUrl(string $state = null)
 * @method static array handleCallback(string $currentUrl, string $expectedState)
 * @method static array getTokenWithClientCredentials(string|array $scope = null)
 * @method static array refreshToken(string $refreshToken)
 * @method static array introspect(string $token)
 * @method static bool revoke(string $token)
 * @method static array getUser(string $accessToken)
 * @method static array switchRole(string $accessToken, string $role)
 * @method static bool checkAccess(string $accessToken, string $resource)
 *
 * @see \Stimata\Portal\StimataClient
 */
class Stimata extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'stimata';
    }
}
