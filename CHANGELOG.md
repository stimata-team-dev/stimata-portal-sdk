# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-XX

### Added

- OAuth2 Client Credentials flow for machine-to-machine authentication
- OAuth2 Authorization Code flow for web applications
- Automatic token refresh mechanism
- Token introspection and revocation
- User profile retrieval
- Role switching functionality
- Resource-based access control
- Native Laravel 8-13 support with auto-discovery
- Service Provider for Laravel integration
- Facade for convenient static method access
- Authentication middleware with auto token refresh
- Authorization middleware for resource permissions
- 11 helper functions for common operations
- Publishable configuration file
- CSRF protection with state parameter
- Comprehensive documentation
- PHPUnit test suite
- GitHub Actions CI/CD pipeline

### Supported Versions

- PHP: 7.4, 8.0, 8.1, 8.2, 8.3
- Laravel: 8.x, 9.x, 10.x, 11.x, 12.x, 13.x

---

## Upgrade Guide

### From 0.x to 1.0

#### Namespace Change
The namespace has changed from `Stimata` to `Stimata\Portal`:

**Before:**
```php
use Stimata\StimataClient;
$client = new StimataClient($config);
```

**After:**
```php
use Stimata\Portal\StimataClient;
$client = new StimataClient($config);

// Or use the facade
use Stimata\Portal\Facades\Stimata;
Stimata::getUser($token);
```

#### Configuration
Publish the configuration file:
```bash
php artisan vendor:publish --tag=stimata-config
```

Update your `.env` file with the required variables.

#### Middleware Registration

**Laravel 8-10:**
```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'stimata.auth' => \Stimata\Portal\Middleware\StimataAuth::class,
    'stimata.access' => \Stimata\Portal\Middleware\StimataCheckAccess::class,
];
```

**Laravel 11+:**
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'stimata.auth' => \Stimata\Portal\Middleware\StimataAuth::class,
        'stimata.access' => \Stimata\Portal\Middleware\StimataCheckAccess::class,
    ]);
})
```

---

[1.0.0]: https://github.com/your-org/stimata-portal-sdk/releases/tag/v1.0.0