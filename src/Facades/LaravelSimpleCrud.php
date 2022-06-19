<?php

namespace Dviluk\LaravelSimpleCrud\Facades;

use Illuminate\Support\Facades\Facade;

class LaravelSimpleCrud extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-simple-crud';
    }
}
