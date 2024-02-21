<?php

namespace Dviluk\LaravelSimpleCrud\Utils\API;

class Error500 extends Error
{
    /**
     * Estado de la respuesta.
     *
     * @var integer
     */
    protected $status = 500;

    public function __construct(array $extra = [], string $message = null, string $response = 'Internal Server Error')
    {
        parent::__construct($extra, $message, $response);
    }
}
