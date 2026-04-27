<?php

namespace Stimata\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Stimata\Portal\StimataClient;

class StimataClientTest extends TestCase
{
    protected $client;
    protected $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect_uri' => 'http://localhost/callback',
            'base_url' => 'http://localhost:9091/api',
            'scopes' => ['openid', 'profile', 'email'],
        ];

        $this->client = new StimataClient($this->config);
    }

    public function testConstructorThrowsExceptionWhenClientIdMissing()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ClientID and ClientSecret are required');

        new StimataClient([
            'client_secret' => 'test-secret',
        ]);
    }

    public function testConstructorThrowsExceptionWhenClientSecretMissing()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ClientID and ClientSecret are required');

        new StimataClient([
            'client_id' => 'test-id',
        ]);
    }

    public function testConstructorAcceptsValidConfig()
    {
        $client = new StimataClient($this->config);
        $this->assertInstanceOf(StimataClient::class, $client);
    }

    public function testGetAuthUrlReturnsArrayWithUrlAndState()
    {
        $result = $this->client->getAuthUrl();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('state', $result);
        $this->assertStringContainsString('oauth/authorize', $result['url']);
        $this->assertStringContainsString('client_id=test-client-id', $result['url']);
        $this->assertStringContainsString('response_type=code', $result['url']);
    }

    public function testGetAuthUrlWithCustomState()
    {
        $customState = 'my-custom-state-12345';
        $result = $this->client->getAuthUrl($customState);

        $this->assertEquals($customState, $result['state']);
        $this->assertStringContainsString('state=' . $customState, $result['url']);
    }

    public function testGetAuthUrlIncludesScopes()
    {
        $result = $this->client->getAuthUrl();

        $expectedScopes = urlencode('openid profile email');
        $this->assertStringContainsString('scope=' . $expectedScopes, $result['url']);
    }

    public function testGetAuthUrlIncludesRedirectUri()
    {
        $result = $this->client->getAuthUrl();

        $expectedRedirectUri = urlencode($this->config['redirect_uri']);
        $this->assertStringContainsString('redirect_uri=' . $expectedRedirectUri, $result['url']);
    }

    public function testHandleCallbackThrowsExceptionWhenNoQueryParameters()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No query parameters found in URL');

        $this->client->handleCallback('http://localhost/callback');
    }

    public function testHandleCallbackThrowsExceptionWhenCodeMissing()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Missing 'code' parameter in callback URL");

        $url = 'http://localhost/callback?state=test-state';
        $this->client->handleCallback($url, 'test-state');
    }

    public function testHandleCallbackThrowsExceptionOnStateMismatch()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid state parameter (potential CSRF)');

        $url = 'http://localhost/callback?code=test-code&state=wrong-state';
        $this->client->handleCallback($url, 'expected-state');
    }

    public function testHandleCallbackThrowsExceptionWhenOAuthErrorPresent()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OAuth Error: Access denied');

        $url = 'http://localhost/callback?error=access_denied&error_description=Access+denied';
        $this->client->handleCallback($url, 'test-state');
    }

    public function testBaseUrlIsNormalizedWithoutTrailingSlash()
    {
        $configWithTrailingSlash = array_merge($this->config, [
            'base_url' => 'http://localhost:9091/api/',
        ]);

        $client = new StimataClient($configWithTrailingSlash);
        $result = $client->getAuthUrl();

        // The URL should not have double slashes
        $this->assertStringNotContainsString('api//oauth', $result['url']);
        $this->assertStringContainsString('api/oauth', $result['url']);
    }

    public function testDefaultBaseUrlWhenNotProvided()
    {
        $configWithoutBaseUrl = [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ];

        $client = new StimataClient($configWithoutBaseUrl);
        $result = $client->getAuthUrl();

        $this->assertStringContainsString('http://localhost:9091/api/oauth', $result['url']);
    }

    public function testDefaultScopesWhenNotProvided()
    {
        $configWithoutScopes = [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ];

        $client = new StimataClient($configWithoutScopes);
        $result = $client->getAuthUrl();

        $expectedScopes = urlencode('openid profile email');
        $this->assertStringContainsString('scope=' . $expectedScopes, $result['url']);
    }

    /**
     * Test that the state parameter is cryptographically random
     */
    public function testGeneratedStateIsUnique()
    {
        $result1 = $this->client->getAuthUrl();
        $result2 = $this->client->getAuthUrl();

        $this->assertNotEquals($result1['state'], $result2['state']);
        $this->assertEquals(32, strlen($result1['state'])); // 16 bytes = 32 hex chars
        $this->assertEquals(32, strlen($result2['state']));
    }

    /**
     * Test configuration array handling
     */
    public function testConfigurationArrayHandling()
    {
        $config = [
            'client_id' => 'test-id',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'http://example.com/callback',
            'base_url' => 'https://auth.example.com/api',
            'scopes' => ['read', 'write', 'admin'],
        ];

        $client = new StimataClient($config);
        $result = $client->getAuthUrl();

        $this->assertStringContainsString('client_id=test-id', $result['url']);
        $this->assertStringContainsString('https://auth.example.com/api', $result['url']);
        $this->assertStringContainsString(urlencode('http://example.com/callback'), $result['url']);
        $this->assertStringContainsString(urlencode('read write admin'), $result['url']);
    }

    /**
     * Test empty string handling
     */
    public function testEmptyClientIdThrowsException()
    {
        $this->expectException(\Exception::class);

        new StimataClient([
            'client_id' => '',
            'client_secret' => 'test-secret',
        ]);
    }

    public function testEmptyClientSecretThrowsException()
    {
        $this->expectException(\Exception::class);

        new StimataClient([
            'client_id' => 'test-id',
            'client_secret' => '',
        ]);
    }

    /**
     * Test scope handling with different formats
     */
    public function testScopesAsString()
    {
        $config = array_merge($this->config, [
            'scopes' => 'read write',
        ]);

        $client = new StimataClient($config);
        $result = $client->getAuthUrl();

        // If scopes is not an array, it should be used as-is
        // But the constructor expects array, so this tests the robustness
        $this->assertIsArray($result);
    }

    /**
     * Test URL encoding in parameters
     */
    public function testSpecialCharactersInConfigAreUrlEncoded()
    {
        $config = array_merge($this->config, [
            'redirect_uri' => 'http://example.com/auth/callback?foo=bar&baz=qux',
        ]);

        $client = new StimataClient($config);
        $result = $client->getAuthUrl();

        // The redirect_uri should be properly URL encoded
        $this->assertStringContainsString(urlencode($config['redirect_uri']), $result['url']);
    }
}
