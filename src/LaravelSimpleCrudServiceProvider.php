<?php

namespace Dviluk\LaravelSimpleCrud;

use Dviluk\LaravelSimpleCrud\Commands\MakeCrud;
use Dviluk\LaravelSimpleCrud\Commands\MakeCRUDController;
use Dviluk\LaravelSimpleCrud\Commands\MakeRepository;
use Dviluk\LaravelSimpleCrud\Commands\MakeUtilsCommand;
use Dviluk\LaravelSimpleCrud\Commands\ResourceMakeCommand;
use Illuminate\Support\ServiceProvider;

class LaravelSimpleCrudServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'dviluk');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'dviluk');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-simple-crud.php', 'laravel-simple-crud');

        // Register the service the package provides.
        $this->app->singleton('laravel-simple-crud', function ($app) {
            return new LaravelSimpleCrud;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['laravel-simple-crud'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/../config/laravel-simple-crud.php' => config_path('laravel-simple-crud.php'),
        ], 'laravel-simple-crud.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/dviluk'),
        ], 'laravel-simple-crud.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/dviluk'),
        ], 'laravel-simple-crud.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/dviluk'),
        ], 'laravel-simple-crud.views');*/

        // Publishing stubs.
        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs'),
        ], 'laravel-simple-crud.stubs');

        // Registering package commands.
        $this->commands([
            MakeCrud::class,
            MakeUtilsCommand::class,
            MakeCRUDController::class,
            MakeRepository::class,
            ResourceMakeCommand::class,
        ]);
    }
}
