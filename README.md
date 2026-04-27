# STIMATA Portal SDK

Official PHP SDK for STIMATA Portal V2 OAuth2 Authorization Server with native Laravel support.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-8%20to%2013-red.svg)](https://laravel.com/)

## Requirements

- PHP 7.4 or higher
- Laravel 8.x - 13.x (for Laravel integration)
- cURL extension
- JSON extension

## Installation

### Via Composer

```bash
composer require stimata/portal-sdk
```

### Laravel Setup (Auto-Discovered)

The package uses Laravel's auto-discovery. After installation, publish the config:

```bash
php artisan vendor:publish --tag=stimata-config
```

### Environment Configuration

Add to your `.env` file:

```env
STIMATA_CLIENT_ID=your-client-id
STIMATA_CLIENT_SECRET=your-client-secret
STIMATA_REDIRECT_URI=https://yourapp.com/auth/callback
STIMATA_BASE_URL=https://auth.stimata.ac.id/api
STIMATA_AUTO_REFRESH=true
STIMATA_SSL_VERIFY=true
```

### Register Middleware

**Laravel 8-10** (`app/Http/Kernel.php`):

```php
protected $routeMiddleware = [
    'stimata.auth' => \Stimata\Portal\Middleware\StimataAuth::class,
    'stimata.access' => \Stimata\Portal\Middleware\StimataCheckAccess::class,
];
```

**Laravel 11+** (`bootstrap/app.php`):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'stimata.auth' => \Stimata\Portal\Middleware\StimataAuth::class,
        'stimata.access' => \Stimata\Portal\Middleware\StimataCheckAccess::class,
    ]);
})
```

## Quick Start

### Laravel Authentication Flow

**1. Create Auth Controller**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StimataAuthController extends Controller
{
    public function login()
    {
        return redirect(stimata_auth_url());
    }

    public function callback()
    {
        try {
            stimata_handle_callback();
            return redirect()->intended('/dashboard');
        } catch (\Exception $e) {
            return redirect('/login')->with('error', $e->getMessage());
        }
    }

    public function logout(Request $request)
    {
        stimata_logout();
        $request->session()->invalidate();
        return redirect('/');
    }
}
```

**2. Define Routes**

```php
Route::get('/login', [StimataAuthController::class, 'login'])->name('login');
Route::get('/auth/callback', [StimataAuthController::class, 'callback']);
Route::post('/logout', [StimataAuthController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware(['stimata.auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

**3. Use in Blade Templates**

```blade
@if(stimata_is_authenticated())
    <p>Welcome, {{ stimata_user()['name'] }}!</p>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
@else
    <a href="{{ route('login') }}">Login with STIMATA</a>
@endif
```

### Standard PHP Usage

```php
<?php

require 'vendor/autoload.php';

use Stimata\Portal\StimataClient;

$client = new StimataClient([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'base_url' => 'https://auth.stimata.ac.id/api',
]);

// Client Credentials (M2M)
$tokenData = $client->getTokenWithClientCredentials('read write');
$accessToken = $tokenData['access_token'];

// Get User
$user = $client->getUser($accessToken);
echo "User: " . $user['name'];
```

## Usage

### Using Facade

```php
use Stimata\Portal\Facades\Stimata;

// Get user profile
$user = Stimata::getUser($token);

// Check access
$canAccess = Stimata::checkAccess($token, 'users.write');

// Get token (client credentials)
$tokenData = Stimata::getTokenWithClientCredentials('read write');

// Refresh token
$newToken = Stimata::refreshToken($refreshToken);

// Revoke token
Stimata::revoke($refreshToken);
```

### Using Helper Functions

```php
// Check authentication
if (stimata_is_authenticated()) {
    $user = stimata_user();
    echo "Welcome, " . $user['name'];
}

// Check permissions
if (stimata_check_access('users.write')) {
    // Show edit button
}

// Get tokens
$token = stimata_token();
$refreshToken = stimata_refresh_token();

// Logout
stimata_logout();

// Client credentials
$tokenData = stimata_client_credentials('read write');

// Switch role
stimata_switch_role('admin');
```

### Using Dependency Injection

```php
use Stimata\Portal\StimataClient;

class UserController extends Controller
{
    public function __construct(protected StimataClient $stimata)
    {
    }
    
    public function index()
    {
        $token = stimata_token();
        $user = $this->stimata->getUser($token);
        
        return view('users.index', compact('user'));
    }
}
```

## API Methods

### Authentication

**Get Authorization URL**
```php
$authData = $client->getAuthUrl($state = null);
// Returns: ['url' => '...', 'state' => '...']
```

**Handle OAuth Callback**
```php
$tokenData = $client->handleCallback($currentUrl, $expectedState);
// Returns: ['access_token' => '...', 'refresh_token' => '...', 'expires_in' => 3600]
```

**Client Credentials Flow**
```php
$tokenData = $client->getTokenWithClientCredentials($scope = null);
// Scope examples: 'read write', ['read', 'write']
```

### Token Management

**Refresh Token**
```php
$tokenData = $client->refreshToken($refreshToken);
```

**Introspect Token**
```php
$info = $client->introspect($token);
// Returns: ['active' => true, 'scope' => '...', 'client_id' => '...', ...]
```

**Revoke Token**
```php
$client->revoke($token); // Returns true/false
```

### User Operations

**Get User Profile**
```php
$user = $client->getUser($accessToken);
// Returns: ['id' => '...', 'email' => '...', 'name' => '...', ...]
```

**Switch Role**
```php
$tokenData = $client->switchRole($accessToken, $role);
```

### Access Control

**Check Resource Access**
```php
$allowed = $client->checkAccess($accessToken, $resource);
// Returns: true or false
```

## Middleware

### Authentication Middleware

Validates and auto-refreshes tokens:

```php
// Single route
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('stimata.auth');

// Route group
Route::middleware(['stimata.auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
});
```

### Access Control Middleware

Check resource permissions:

```php
// Single resource
Route::get('/users', [UserController::class, 'index'])
    ->middleware('stimata.access:users.read');

// Multiple resources (all required)
Route::post('/users', [UserController::class, 'store'])
    ->middleware('stimata.access:users.write,users.create');

// Combined
Route::middleware(['stimata.auth', 'stimata.access:admin.access'])
    ->group(function () {
        Route::get('/admin', [AdminController::class, 'index']);
    });
```

## Helper Functions

| Function | Description |
|----------|-------------|
| `stimata()` | Get client instance or call method |
| `stimata_token()` | Get current access token |
| `stimata_refresh_token()` | Get current refresh token |
| `stimata_user()` | Get authenticated user profile |
| `stimata_check_access($resource)` | Check resource permission |
| `stimata_is_authenticated()` | Check if user is authenticated |
| `stimata_auth_url()` | Generate authentication URL |
| `stimata_handle_callback($url)` | Handle OAuth callback |
| `stimata_logout()` | Logout and revoke tokens |
| `stimata_client_credentials($scope, $store)` | Get M2M token |
| `stimata_switch_role($role)` | Switch user role |

## Examples

### Service-to-Service Authentication

```php
use Stimata\Portal\Facades\Stimata;
use Illuminate\Support\Facades\Http;

class ExternalApiService
{
    protected $accessToken;
    
    public function __construct()
    {
        $tokenData = Stimata::getTokenWithClientCredentials('api.access');
        $this->accessToken = $tokenData['access_token'];
    }
    
    public function fetchData()
    {
        return Http::withToken($this->accessToken)
            ->get('https://api.example.com/data')
            ->json();
    }
}
```

### Check Permissions in Controller

```php
class UserController extends Controller
{
    public function edit($id)
    {
        if (!stimata_check_access('users.write')) {
            abort(403, 'You do not have permission to edit users');
        }
        
        $user = User::findOrFail($id);
        return view('users.edit', compact('user'));
    }
}
```

### Custom Token Validation

```php
use Stimata\Portal\Facades\Stimata;

class ApiController extends Controller
{
    public function getData(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json(['error' => 'No token provided'], 401);
        }
        
        try {
            $introspection = Stimata::introspect($token);
            
            if (!$introspection['active']) {
                return response()->json(['error' => 'Invalid token'], 401);
            }
            
            if (!Stimata::checkAccess($token, 'api.read')) {
                return response()->json(['error' => 'Insufficient permissions'], 403);
            }
            
            return response()->json(['data' => [/* your data */]]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication error'], 500);
        }
    }
}
```

### Blade Permission Checks

```blade
{{-- Check single permission --}}
@if(stimata_check_access('users.write'))
    <a href="{{ route('users.edit', $user) }}">Edit</a>
@endif

{{-- Check multiple permissions --}}
@php
    $permissions = [
        'read' => stimata_check_access('users.read'),
        'write' => stimata_check_access('users.write'),
        'delete' => stimata_check_access('users.delete'),
    ];
@endphp

@if($permissions['delete'])
    <form method="POST" action="{{ route('users.destroy', $user) }}">
        @csrf
        @method('DELETE')
        <button type="submit">Delete</button>
    </form>
@endif
```

## Configuration

### Available Options

```php
// config/stimata.php

return [
    'client_id' => env('STIMATA_CLIENT_ID'),
    'client_secret' => env('STIMATA_CLIENT_SECRET'),
    'redirect_uri' => env('STIMATA_REDIRECT_URI'),
    'base_url' => env('STIMATA_BASE_URL'),
    
    'scopes' => ['openid', 'profile', 'email'],
    
    'token_storage' => env('STIMATA_TOKEN_STORAGE', 'session'),
    'cache_driver' => env('STIMATA_CACHE_DRIVER', 'redis'),
    'refresh_buffer' => env('STIMATA_REFRESH_BUFFER', 300),
    'auto_refresh' => env('STIMATA_AUTO_REFRESH', true),
    'ssl_verify' => env('STIMATA_SSL_VERIFY', true),
    'timeout' => env('STIMATA_TIMEOUT', 30),
    
    'middleware' => [
        'redirect_on_failure' => env('STIMATA_REDIRECT_ON_FAILURE', '/login'),
        'session_token_key' => 'stimata_access_token',
        'session_refresh_key' => 'stimata_refresh_token',
        'session_expires_key' => 'stimata_token_expires_at',
    ],
    
    'logging' => [
        'enabled' => env('STIMATA_LOGGING', false),
        'channel' => env('STIMATA_LOG_CHANNEL', 'stack'),
        'level' => env('STIMATA_LOG_LEVEL', 'debug'),
    ],
];
```

## Error Handling

All methods throw `\Exception` on error. Always use try-catch:

```php
try {
    $tokenData = Stimata::getTokenWithClientCredentials('read write');
} catch (\Exception $e) {
    $code = $e->getCode();
    $message = $e->getMessage();
    
    switch ($code) {
        case 400:
            // Bad request (invalid scope, etc.)
            Log::error("Invalid request: {$message}");
            break;
        case 401:
            // Unauthorized (invalid credentials)
            Log::error("Authentication failed");
            break;
        case 403:
            // Forbidden (no access to resource)
            Log::error("Access denied");
            break;
        default:
            Log::error("Error: {$message}");
    }
}
```

## Troubleshooting

### Common Issues

**"ClientID and ClientSecret are required"**
- Ensure `STIMATA_CLIENT_ID` and `STIMATA_CLIENT_SECRET` are set in `.env`
- Run `php artisan config:clear`

**"Invalid state parameter (potential CSRF)"**
- Ensure sessions are working properly
- Check session driver in `.env`: `SESSION_DRIVER=database`
- Run `php artisan session:table && php artisan migrate`

**Middleware not found**
- Clear caches: `php artisan route:clear && php artisan cache:clear`
- Verify middleware is registered in Kernel or bootstrap/app.php

**SSL Certificate Verification Failed**
- Development only: Set `STIMATA_SSL_VERIFY=false` in `.env`
- Production: Ensure server has up-to-date CA certificates

**Token Refresh Failing**
- Check `STIMATA_AUTO_REFRESH=true`
- Verify `STIMATA_REFRESH_BUFFER=300` (seconds before expiry)
- Enable logging: `STIMATA_LOGGING=true`

### Debug Mode

Enable detailed logging:

```env
STIMATA_LOGGING=true
STIMATA_LOG_CHANNEL=stack
STIMATA_LOG_LEVEL=debug
```

View logs:
```bash
tail -f storage/logs/laravel.log
```

## Version Compatibility

| Laravel Version | PHP Version | Status |
|----------------|-------------|---------|
| 8.x | 7.4 - 8.1 | ✅ Supported |
| 9.x | 8.0 - 8.2 | ✅ Supported |
| 10.x | 8.1 - 8.3 | ✅ Supported |
| 11.x | 8.2 - 8.3 | ✅ Supported |
| 12.x | 8.2+ | ✅ Ready |
| 13.x | 8.3+ | ✅ Ready |

## Testing

```bash
# Run tests
composer test

# Run with coverage
composer test-coverage

# Code quality
composer analyse
composer format
```

## Security

### Best Practices

1. **Never hardcode credentials** - Use environment variables
2. **Enable SSL in production** - `STIMATA_SSL_VERIFY=true`
3. **Use secure session storage** - Database or Redis recommended
4. **Rotate tokens regularly** - Enable auto-refresh
5. **Log security events** - Monitor authentication failures

### Reporting Vulnerabilities

Please report security vulnerabilities to: **engineering@stimata.ac.id**

Do not create public GitHub issues for security vulnerabilities.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and upgrade guides.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

- **Documentation**: This README and [USAGE.md](USAGE.md)
- **Issues**: https://github.com/your-org/stimata-portal-sdk/issues
- **Email**: engineering@stimata.ac.id

## Credits

Maintained by **STIMATA Engineering Team**

---

**Version:** 1.0.0  
**Last Updated:** 2025
