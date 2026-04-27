# STIMATA Portal V2 - PHP SDK Usage Guide

## Installation

### Via Composer (Recommended)

```bash
composer require stimata/portal-sdk
```

### Manual Installation

Download the `StimataClient.php` file and include it in your project:

```php
require_once 'path/to/StimataClient.php';
```

## Configuration

### Basic Configuration

```php
use Stimata\StimataClient;

$client = new StimataClient([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'https://yourapp.com/callback',
    'base_url' => 'https://auth.example.com/api',
    'scopes' => ['openid', 'profile', 'email']
]);
```

### Laravel Configuration

Create a config file `config/stimata.php`:

```php
<?php

return [
    'client_id' => env('STIMATA_CLIENT_ID'),
    'client_secret' => env('STIMATA_CLIENT_SECRET'),
    'redirect_uri' => env('STIMATA_REDIRECT_URI', 'https://yourapp.com/callback'),
    'base_url' => env('STIMATA_BASE_URL', 'http://localhost:9091/api'),
    'scopes' => ['openid', 'profile', 'email'],
];
```

In `.env`:

```env
STIMATA_CLIENT_ID=your-client-id
STIMATA_CLIENT_SECRET=your-client-secret
STIMATA_REDIRECT_URI=https://yourapp.com/callback
STIMATA_BASE_URL=https://auth.example.com/api
```

Usage in Laravel:

```php
$client = new StimataClient(config('stimata'));
```

## Authentication Flows

### 1. Client Credentials (Machine-to-Machine)

Best for server-to-server communication without user context.

```php
<?php

use Stimata\StimataClient;

$client = new StimataClient([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'base_url' => 'https://auth.example.com/api',
]);

try {
    // Get token with default scopes
    $tokenData = $client->getTokenWithClientCredentials();
    
    echo "Access Token: " . $tokenData['access_token'] . "\n";
    echo "Token Type: " . $tokenData['token_type'] . "\n";
    echo "Expires In: " . $tokenData['expires_in'] . " seconds\n";
    echo "Scope: " . $tokenData['scope'] . "\n";
    
    // Store the access token
    $accessToken = $tokenData['access_token'];
    
    // Use the token for API calls
    // ...
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

#### With Custom Scopes

```php
// Request specific scopes as string
$tokenData = $client->getTokenWithClientCredentials('read write');

// Or as array
$tokenData = $client->getTokenWithClientCredentials(['read', 'write', 'admin']);
```

#### Example: Scheduled Task

```php
<?php

// cron job or Laravel scheduled task
$client = new StimataClient(config('stimata'));

try {
    // Get token
    $tokenData = $client->getTokenWithClientCredentials('read write');
    $accessToken = $tokenData['access_token'];
    
    // Use token to call protected API
    $canAccess = $client->checkAccess($accessToken, 'users.read');
    
    if ($canAccess) {
        // Perform automated task
        echo "Access granted. Processing...\n";
    }
    
} catch (Exception $e) {
    error_log("Scheduled task failed: " . $e->getMessage());
}
```

### 2. Authorization Code Flow (Web Applications)

Best for web applications with user authentication.

#### Step 1: Redirect to Authorization

```php
<?php
session_start();

$client = new StimataClient(config('stimata'));

// Generate authorization URL
$authData = $client->getAuthUrl();

// Store state in session for CSRF protection
$_SESSION['oauth_state'] = $authData['state'];

// Redirect user to authorization page
header('Location: ' . $authData['url']);
exit;
```

#### Step 2: Handle Callback

```php
<?php
// callback.php
session_start();

$client = new StimataClient(config('stimata'));

try {
    // Get current URL
    $currentUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . 
                  "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    // Handle callback and exchange code for token
    $tokenData = $client->handleCallback($currentUrl, $_SESSION['oauth_state']);
    
    // Store tokens in session
    $_SESSION['access_token'] = $tokenData['access_token'];
    $_SESSION['refresh_token'] = $tokenData['refresh_token'];
    $_SESSION['expires_in'] = $tokenData['expires_in'];
    
    // Get user profile
    $user = $client->getUser($tokenData['access_token']);
    $_SESSION['user'] = $user;
    
    // Redirect to dashboard
    header('Location: /dashboard');
    exit;
    
} catch (Exception $e) {
    echo "Authentication failed: " . $e->getMessage();
}
```

#### Laravel Example

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stimata\StimataClient;

class AuthController extends Controller
{
    private $client;
    
    public function __construct()
    {
        $this->client = new StimataClient(config('stimata'));
    }
    
    // Redirect to login
    public function redirectToProvider()
    {
        $authData = $this->client->getAuthUrl();
        session(['oauth_state' => $authData['state']]);
        
        return redirect($authData['url']);
    }
    
    // Handle callback
    public function handleProviderCallback(Request $request)
    {
        try {
            $currentUrl = $request->fullUrl();
            $expectedState = session('oauth_state');
            
            $tokenData = $this->client->handleCallback($currentUrl, $expectedState);
            
            // Store tokens
            session([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
            ]);
            
            // Get user
            $user = $this->client->getUser($tokenData['access_token']);
            
            // Login user to your application
            // Auth::login($user);
            
            return redirect('/dashboard');
            
        } catch (\Exception $e) {
            return redirect('/login')->with('error', $e->getMessage());
        }
    }
}
```

### 3. Refresh Token

```php
<?php

$client = new StimataClient(config('stimata'));

try {
    $refreshToken = $_SESSION['refresh_token'];
    
    $tokenData = $client->refreshToken($refreshToken);
    
    // Update stored tokens
    $_SESSION['access_token'] = $tokenData['access_token'];
    $_SESSION['expires_in'] = $tokenData['expires_in'];
    
    echo "Token refreshed successfully!";
    
} catch (Exception $e) {
    echo "Failed to refresh token: " . $e->getMessage();
}
```

## Common Operations

### Check Token Validity

```php
$client = new StimataClient(config('stimata'));
$accessToken = $_SESSION['access_token'];

try {
    $introspection = $client->introspect($accessToken);
    
    if ($introspection['active']) {
        echo "Token is valid\n";
        echo "User ID: " . $introspection['user_id'] . "\n";
        echo "Client ID: " . $introspection['client_id'] . "\n";
        echo "Scopes: " . $introspection['scope'] . "\n";
        echo "Expires at: " . date('Y-m-d H:i:s', $introspection['expires_at']) . "\n";
    } else {
        echo "Token is invalid or expired\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Get User Profile

```php
$client = new StimataClient(config('stimata'));
$accessToken = $_SESSION['access_token'];

try {
    $user = $client->getUser($accessToken);
    
    echo "User ID: " . $user['id'] . "\n";
    echo "Name: " . $user['name'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Active: " . ($user['is_active'] ? 'Yes' : 'No') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Switch User Role

```php
$client = new StimataClient(config('stimata'));
$accessToken = $_SESSION['access_token'];

try {
    $tokenData = $client->switchRole($accessToken, 'admin');
    
    // Update access token with new role
    $_SESSION['access_token'] = $tokenData['access_token'];
    
    echo "Switched to admin role successfully!";
    
} catch (Exception $e) {
    echo "Failed to switch role: " . $e->getMessage();
}
```

### Check Access to Resource

```php
$client = new StimataClient(config('stimata'));
$accessToken = $_SESSION['access_token'];

try {
    $canRead = $client->checkAccess($accessToken, 'users.read');
    $canWrite = $client->checkAccess($accessToken, 'users.write');
    $canDelete = $client->checkAccess($accessToken, 'users.delete');
    
    echo "Can read users: " . ($canRead ? 'Yes' : 'No') . "\n";
    echo "Can write users: " . ($canWrite ? 'Yes' : 'No') . "\n";
    echo "Can delete users: " . ($canDelete ? 'Yes' : 'No') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Logout (Revoke Token)

```php
$client = new StimataClient(config('stimata'));
$refreshToken = $_SESSION['refresh_token'];

try {
    $success = $client->revoke($refreshToken);
    
    if ($success) {
        // Clear session
        session_destroy();
        echo "Logged out successfully!";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Complete Examples

### Example 1: Service-to-Service Communication

```php
<?php

use Stimata\StimataClient;

class UserSyncService
{
    private $client;
    private $accessToken;
    
    public function __construct()
    {
        $this->client = new StimataClient([
            'client_id' => getenv('STIMATA_CLIENT_ID'),
            'client_secret' => getenv('STIMATA_CLIENT_SECRET'),
            'base_url' => getenv('STIMATA_BASE_URL'),
        ]);
        
        $this->authenticate();
    }
    
    private function authenticate()
    {
        try {
            $tokenData = $this->client->getTokenWithClientCredentials('read write');
            $this->accessToken = $tokenData['access_token'];
        } catch (Exception $e) {
            throw new Exception("Failed to authenticate: " . $e->getMessage());
        }
    }
    
    public function syncUsers()
    {
        try {
            // Check if we have access
            if (!$this->client->checkAccess($this->accessToken, 'users.read')) {
                throw new Exception("No permission to read users");
            }
            
            // Make API call to get users
            // (You would use your own HTTP client here)
            echo "Syncing users with access token: " . substr($this->accessToken, 0, 20) . "...\n";
            
        } catch (Exception $e) {
            error_log("User sync failed: " . $e->getMessage());
        }
    }
}

// Usage
$service = new UserSyncService();
$service->syncUsers();
```

### Example 2: Laravel Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Stimata\StimataClient;

class StimataAuth
{
    public function handle($request, Closure $next)
    {
        $accessToken = session('access_token');
        
        if (!$accessToken) {
            return redirect('/login');
        }
        
        $client = new StimataClient(config('stimata'));
        
        try {
            // Verify token is still valid
            $introspection = $client->introspect($accessToken);
            
            if (!$introspection['active']) {
                // Try to refresh token
                $refreshToken = session('refresh_token');
                if ($refreshToken) {
                    $tokenData = $client->refreshToken($refreshToken);
                    session(['access_token' => $tokenData['access_token']]);
                } else {
                    return redirect('/login');
                }
            }
            
            return $next($request);
            
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Session expired');
        }
    }
}
```

### Example 3: Check Resource Access

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Stimata\StimataClient;

class CheckResourceAccess
{
    public function handle($request, Closure $next, $resource)
    {
        $accessToken = session('access_token');
        $client = new StimataClient(config('stimata'));
        
        try {
            $hasAccess = $client->checkAccess($accessToken, $resource);
            
            if (!$hasAccess) {
                abort(403, 'You do not have access to this resource');
            }
            
            return $next($request);
            
        } catch (\Exception $e) {
            abort(500, 'Failed to check access: ' . $e->getMessage());
        }
    }
}

// Usage in routes
Route::get('/users', [UserController::class, 'index'])
    ->middleware('check.resource:users.read');

Route::post('/users', [UserController::class, 'store'])
    ->middleware('check.resource:users.write');
```

## Error Handling

All methods throw `Exception` on error. Always wrap calls in try-catch:

```php
try {
    $tokenData = $client->getTokenWithClientCredentials();
} catch (Exception $e) {
    // Log error
    error_log('OAuth Error: ' . $e->getMessage());
    
    // Check error code
    $code = $e->getCode();
    if ($code === 401) {
        // Invalid credentials
    } elseif ($code === 400) {
        // Bad request (e.g., invalid scope)
    }
    
    // Show user-friendly message
    echo "Authentication failed. Please try again.";
}
```

## Common Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request (invalid parameters, invalid scope) |
| 401 | Unauthorized (invalid credentials) |
| 403 | Forbidden (no access to resource) |
| 404 | Not Found |
| 500 | Internal Server Error |

## Best Practices

### 1. Token Storage

**Session Storage (Web Apps):**
```php
$_SESSION['access_token'] = $tokenData['access_token'];
$_SESSION['refresh_token'] = $tokenData['refresh_token'];
$_SESSION['expires_at'] = time() + $tokenData['expires_in'];
```

**Database Storage (Long-lived):**
```php
DB::table('oauth_tokens')->insert([
    'user_id' => $userId,
    'access_token' => $tokenData['access_token'],
    'refresh_token' => $tokenData['refresh_token'],
    'expires_at' => now()->addSeconds($tokenData['expires_in']),
]);
```

### 2. Token Refresh

Always check token expiry before use:

```php
function getValidToken()
{
    $expiresAt = $_SESSION['expires_at'] ?? 0;
    
    if (time() >= $expiresAt - 60) { // Refresh 1 minute before expiry
        $client = new StimataClient(config('stimata'));
        $tokenData = $client->refreshToken($_SESSION['refresh_token']);
        
        $_SESSION['access_token'] = $tokenData['access_token'];
        $_SESSION['expires_at'] = time() + $tokenData['expires_in'];
    }
    
    return $_SESSION['access_token'];
}
```

### 3. Scope Management

Request only the scopes you need:

```php
// ❌ Bad - requesting unnecessary scopes
$tokenData = $client->getTokenWithClientCredentials('read write delete admin');

// ✅ Good - request only what you need
$tokenData = $client->getTokenWithClientCredentials('read');
```

### 4. Security

**Enable SSL in Production:**
```php
// In StimataClient.php, uncomment:
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
```

**Never expose credentials:**
```php
// ❌ Bad
$client = new StimataClient([
    'client_id' => 'hardcoded-id',
    'client_secret' => 'hardcoded-secret',
]);

// ✅ Good
$client = new StimataClient([
    'client_id' => env('STIMATA_CLIENT_ID'),
    'client_secret' => env('STIMATA_CLIENT_SECRET'),
]);
```

## Troubleshooting

### Invalid Scope Error

```
Exception: scope(s) not allowed: admin
```

**Solution:** Check that the scope is configured in the application's `allowed_scopes`.

### Token Expired

```
Exception: Token has expired
```

**Solution:** Use refresh token to get a new access token.

### CSRF Error

```
Exception: Invalid state parameter (potential CSRF)
```

**Solution:** Ensure the state parameter is properly stored and validated.

## Support

For issues and questions:
- Documentation: [docs/README.md](../../docs/README.md)
- API Reference: [docs/API_REFERENCE.md](../../docs/API_REFERENCE.md)

---

**Version:** 2.0  
**Last Updated:** 2025-01-XX