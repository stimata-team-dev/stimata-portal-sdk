<?php

namespace Stimata\Portal;

use Illuminate\Support\ServiceProvider;

class StimataServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Merge package config with app config
        $this->mergeConfigFrom(
            __DIR__.'/../config/stimata.php',
            'stimata'
        );

        // Register the main client as a singleton
        $this->app->singleton('stimata', function ($app) {
            return new StimataClient($app['config']['stimata']);
        });

        // Register alias
        $this->app->alias('stimata', StimataClient::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/stimata.php' => config_path('stimata.php'),
            ], 'stimata-config');

            // Publish middleware (optional)
            $this->publishes([
                __DIR__.'/Middleware/StimataAuth.php' => app_path('Http/Middleware/StimataAuth.php'),
                __DIR__.'/Middleware/StimataCheckAccess.php' => app_path('Http/Middleware/StimataCheckAccess.php'),
            ], 'stimata-middleware');
        }

        // Register middleware aliases
        $router = $this->app['router'];

        // For Laravel 11+ compatibility
        if (method_exists($router, 'aliasMiddleware')) {
            $router->aliasMiddleware('stimata.auth', \Stimata\Portal\Middleware\StimataAuth::class);
            $router->aliasMiddleware('stimata.access', \Stimata\Portal\Middleware\StimataCheckAccess::class);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['stimata', StimataClient::class];
    }
}
