<?php

namespace Dviluk\LaravelSimpleCrud\Utils\API;

class Error400 extends Error
{
    /**
     * Estado de la respuesta.
     *
     * @var integer
     */
    protected $status = 400;

    public function __construct(array $extra = [], string $message = null, string $response = 'Bad Request')
    {
        parent::__construct($extra, $message, $response);
    }
}
