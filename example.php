<?php

require_once __DIR__ . '/src/StimataClient.php';

use Stimata\StimataClient;

/**
 * STIMATA Portal V2 - PHP SDK Examples
 *
 * This file demonstrates various usage patterns for the STIMATA Portal PHP SDK
 */

// Configuration
$config = [
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'https://yourapp.com/callback',
    'base_url' => 'http://localhost:9091/api',
    'scopes' => ['openid', 'profile', 'email']
];

echo "=== STIMATA Portal V2 - PHP SDK Examples ===\n\n";

// ============================================================================
// Example 1: Client Credentials Flow (Machine-to-Machine)
// ============================================================================
echo "Example 1: Client Credentials Flow\n";
echo "-----------------------------------\n";

try {
    $client = new StimataClient($config);

    // Get token with client credentials
    $tokenData = $client->getTokenWithClientCredentials('read write');

    echo "✅ Token obtained successfully!\n";
    echo "Access Token: " . substr($tokenData['access_token'], 0, 50) . "...\n";
    echo "Token Type: " . $tokenData['token_type'] . "\n";
    echo "Expires In: " . $tokenData['expires_in'] . " seconds\n";
    echo "Scope: " . $tokenData['scope'] . "\n\n";

    // Store token for later use
    $accessToken = $tokenData['access_token'];

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// ============================================================================
// Example 2: Check Token Validity
// ============================================================================
echo "Example 2: Token Introspection\n";
echo "-------------------------------\n";

try {
    $introspection = $client->introspect($accessToken);

    if ($introspection['active']) {
        echo "✅ Token is active\n";
        echo "Client ID: " . $introspection['client_id'] . "\n";
        echo "Scope: " . $introspection['scope'] . "\n";
        echo "Expires at: " . date('Y-m-d H:i:s', $introspection['expires_at']) . "\n\n";
    } else {
        echo "❌ Token is inactive\n\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// Example 3: Check Resource Access
// ============================================================================
echo "Example 3: Check Resource Access\n";
echo "---------------------------------\n";

try {
    $resources = ['users.read', 'users.write', 'users.delete'];

    foreach ($resources as $resource) {
        try {
            $hasAccess = $client->checkAccess($accessToken, $resource);
            $status = $hasAccess ? '✅ Allowed' : '❌ Denied';
            echo "$status - $resource\n";
        } catch (Exception $e) {
            echo "❌ Error checking $resource: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// Example 4: Authorization Code Flow (Web Application)
// ============================================================================
echo "Example 4: Authorization Code Flow\n";
echo "-----------------------------------\n";

try {
    // Step 1: Generate authorization URL
    $authData = $client->getAuthUrl();

    echo "Step 1 - Redirect user to:\n";
    echo $authData['url'] . "\n\n";
    echo "Step 2 - Store state in session:\n";
    echo "State: " . $authData['state'] . "\n\n";
    echo "Step 3 - After user authorizes, handle callback with:\n";
    echo "\$tokenData = \$client->handleCallback(\$currentUrl, \$storedState);\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// Example 5: Get User Profile (requires user token)
// ============================================================================
echo "Example 5: Get User Profile\n";
echo "----------------------------\n";

// Note: This requires a user access token from authorization code flow
// For demonstration, we'll show the code pattern
echo "Code pattern for getting user profile:\n";
echo <<<'CODE'
try {
    $user = $client->getUser($userAccessToken);

    echo "User ID: " . $user['id'] . "\n";
    echo "Name: " . $user['name'] . "\n";
    echo "Email: " . $user['email'] . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
CODE;
echo "\n\n";

// ============================================================================
// Example 6: Refresh Token
// ============================================================================
echo "Example 6: Refresh Token\n";
echo "------------------------\n";

echo "Code pattern for refreshing tokens:\n";
echo <<<'CODE'
try {
    $tokenData = $client->refreshToken($refreshToken);

    // Update stored tokens
    $newAccessToken = $tokenData['access_token'];
    $expiresIn = $tokenData['expires_in'];

    echo "Token refreshed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
CODE;
echo "\n\n";

// ============================================================================
// Example 7: Switch Role
// ============================================================================
echo "Example 7: Switch Role\n";
echo "----------------------\n";

echo "Code pattern for switching user role:\n";
echo <<<'CODE'
try {
    $tokenData = $client->switchRole($userAccessToken, 'admin');

    // Update access token with new role
    $newAccessToken = $tokenData['access_token'];

    echo "Switched to admin role!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
CODE;
echo "\n\n";

// ============================================================================
// Example 8: Revoke Token (Logout)
// ============================================================================
echo "Example 8: Revoke Token\n";
echo "-----------------------\n";

echo "Code pattern for revoking refresh token:\n";
echo <<<'CODE'
try {
    $success = $client->revoke($refreshToken);

    if ($success) {
        // Clear session
        session_destroy();
        echo "Logged out successfully!\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
CODE;
echo "\n\n";

// ============================================================================
// Example 9: Error Handling
// ============================================================================
echo "Example 9: Proper Error Handling\n";
echo "---------------------------------\n";

echo "Always use try-catch blocks:\n";
echo <<<'CODE'
try {
    $tokenData = $client->getTokenWithClientCredentials('invalid-scope');

} catch (Exception $e) {
    $code = $e->getCode();
    $message = $e->getMessage();

    if ($code === 400 && strpos($message, 'invalid_scope') !== false) {
        echo "Scope not allowed. Check application configuration.\n";
    } elseif ($code === 401) {
        echo "Invalid credentials. Check client_id and client_secret.\n";
    } else {
        echo "Error: " . $message . "\n";
    }
}
CODE;
echo "\n\n";

// ============================================================================
// Example 10: Complete Service-to-Service Example
// ============================================================================
echo "Example 10: Complete Service-to-Service Pattern\n";
echo "------------------------------------------------\n";

try {
    // Step 1: Authenticate with client credentials
    $tokenData = $client->getTokenWithClientCredentials('read write');
    $serviceToken = $tokenData['access_token'];

    echo "✅ Step 1: Authenticated successfully\n";

    // Step 2: Verify token is valid
    $introspection = $client->introspect($serviceToken);
    if (!$introspection['active']) {
        throw new Exception("Token is not active");
    }

    echo "✅ Step 2: Token verified as active\n";

    // Step 3: Check access to required resource
    $canRead = $client->checkAccess($serviceToken, 'users.read');
    if (!$canRead) {
        throw new Exception("No permission to read users");
    }

    echo "✅ Step 3: Access to resource confirmed\n";

    // Step 4: Perform API operations
    echo "✅ Step 4: Ready to perform API operations\n";
    echo "\nService-to-service authentication complete!\n";

} catch (Exception $e) {
    echo "❌ Service authentication failed: " . $e->getMessage() . "\n";
}

echo "\n=== Examples Complete ===\n";
