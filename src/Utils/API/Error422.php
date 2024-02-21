<?php

namespace Dviluk\LaravelSimpleCrud\Utils\API;

class Error422 extends Error
{
    /**
     * Estado de la respuesta.
     *
     * @var integer
     */
    protected $status = 422;

    public function __construct(array $extra = [], string $message = null, string $response = 'Invalid Input')
    {
        parent::__construct($extra, $message, $response);
    }
}
