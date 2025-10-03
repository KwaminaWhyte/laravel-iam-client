<?php

namespace Adamus\LaravelIamClient;

use Adamus\LaravelIamClient\Auth\IAMGuard;
use Adamus\LaravelIamClient\Auth\IAMUserProvider;
use Adamus\LaravelIamClient\Console\InstallCommand;
use Adamus\LaravelIamClient\Console\InstallClientCommand;
use Adamus\LaravelIamClient\Console\InstallServerCommand;
use Adamus\LaravelIamClient\Http\Middleware\IAMAuthenticate;
use Adamus\LaravelIamClient\Http\Middleware\IAMSessionAuth;
use Adamus\LaravelIamClient\Services\IAMService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class IAMClientServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/iam.php', 'iam'
        );

        // Register IAM Service as singleton
        $this->app->singleton(IAMService::class, function ($app) {
            return new IAMService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register custom auth guard
        Auth::extend('iam', function ($app, $name, array $config) {
            $provider = Auth::createUserProvider($config['provider']);
            $iamService = $app->make(IAMService::class);

            return new IAMGuard($provider, $app['request'], $iamService);
        });

        // Register custom user provider
        Auth::provider('iam', function ($app, array $config) {
            $iamService = $app->make(IAMService::class);
            return new IAMUserProvider($iamService, $config);
        });

        // Register middleware aliases
        $this->app['router']->aliasMiddleware('iam.auth', IAMSessionAuth::class);
        $this->app['router']->aliasMiddleware('iam.authenticate', IAMAuthenticate::class);

        // Load routes - they will be wrapped in web middleware by the application
        $this->loadRoutesFrom(__DIR__.'/../routes/auth.php');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/iam.php' => config_path('iam.php'),
        ], 'iam-config');

        // Publish views (React component)
        $this->publishes([
            __DIR__.'/../resources/js/pages/auth' => resource_path('js/pages/auth'),
        ], 'iam-views');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                InstallClientCommand::class,
                InstallServerCommand::class,
            ]);
        }
    }
}