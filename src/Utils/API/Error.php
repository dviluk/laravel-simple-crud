<?php

namespace Dviluk\LaravelSimpleCrud\Utils\API;

use Dviluk\LaravelSimpleCrud\Contracts\ErrorResponseInterface;
use Exception;

class Error extends Exception implements ErrorResponseInterface
{
    /**
     * Estado de la respuesta.
     *
     * @var integer
     */
    protected $status;

    /**
     * Respuesta para el cliente.
     *
     * @var string
     */
    protected $response;

    /**
     * Mensaje para el cliente.
     *
     * @var string
     */
    protected $message;

    /**
     * Contenido que se agregara a la respuesta.
     *
     * @var array
     */
    protected $extra;

    public function __construct(array $extra = [], ?string $message, string $response)
    {
        $this->response = $response;
        $this->extra = $extra;

        if (!$this->status) {
            throw new Exception('El campo `status` no esta definido.');
        }

        // make sure everything is assigned properly
        parent::__construct($message ?? $response, 5000);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function getResponse(): string
    {
        return  $this->response;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getExtra(): array
    {
        return  $this->extra;
    }
}
