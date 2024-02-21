<?php

namespace Dviluk\LaravelSimpleCrud\Utils\API;

class Error401 extends Error
{
    /**
     * Estado de la respuesta.
     *
     * @var integer
     */
    protected $status = 401;

    public function __construct(array $extra = [], string $message = null, string $response = 'Unauthenticated')
    {
        parent::__construct($extra, $message, $response);
    }
}
