<?php

namespace Dviluk\LaravelSimpleCrud\Utils\API;

class Error419 extends Error
{
    /**
     * Estado de la respuesta.
     *
     * @var integer
     */
    protected $status = 419;

    public function __construct(array $extra = [], string $message = null, string $response = 'Session Expired')
    {
        parent::__construct($extra, $message, $response);
    }
}
