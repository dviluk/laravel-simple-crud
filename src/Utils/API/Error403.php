<?php

namespace Dviluk\LaravelSimpleCrud\Utils\API;

class Error403 extends Error
{
    /**
     * Estado de la respuesta.
     *
     * @var integer
     */
    protected $status = 403;

    public function __construct(array $extra = [], string $message = null, string $response = 'Forbidden')
    {
        parent::__construct($extra, $message, $response);
    }
}
