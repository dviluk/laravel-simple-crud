<?php

namespace Dviluk\LaravelSimpleCrud;

use Dviluk\LaravelSimpleCrud\Contracs\ErrorResponseInterface;
use \Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Contiene método útiles para realizar peticiones.
 */
class API
{
    /**
     * La petición ha sido completada correctamente.
     * 
     * @param string $message Mensaje descriptivo de la acción realizada
     * @return \Illuminate\Http\JsonResponse
     */
    public static function response200($extra = [], $message = 'Success')
    {
        return self::prepareResponse(200, $message, $extra);
    }

    /**
     * Se creó correctamente el recurso.
     * 
     * @param string $message Mensaje descriptivo de la acción realizada
     * @return \Illuminate\Http\JsonResponse
     */
    public static function response201($extra = [], $message = 'Created')
    {
        return self::prepareResponse(200, $message, $extra);
    }

    /**
     * Los datos de la petición no fueron entendidos por el servidor.
     * 
     * @param string $message Mensaje descriptivo de la acción realizada
     * @return \Illuminate\Http\JsonResponse
     */
    public static function response400($extra = [], $message = 'Bad Request')
    {
        return self::prepareResponse(400, $message, $extra);
    }

    /**
     * La petición require autenticación del usuario.
     * 
     * @param string $message Mensaje descriptivo de la acción realizada
     * @return \Illuminate\Http\JsonResponse
     */
    public static function response401($extra = [], $message = 'Unauthenticated')
    {
        return self::prepareResponse(401, $message, $extra);
    }

    /**
     * No tiene permiso para completar la acción de la petición.
     * 
     * @param string $message Mensaje descriptivo de la acción realizada
     * @return \Illuminate\Http\JsonResponse
     */
    public static function response403($extra = [], $message = 'Forbidden')
    {
        return self::prepareResponse(403, $message, $extra);
    }

    /**
     * El recurso que solicita no se encontró.
     * 
     * @param string $message Mensaje descriptivo de la acción realizada
     * @return \Illuminate\Http\JsonResponse
     */
    public static function response404($extra = [], $message = 'Not Found')
    {
        return self::prepareResponse(404, $message, $extra);
    }

    /**
     * El recurso que solicita no se encontró.
     * 
     * @param string $message Mensaje descriptivo de la acción realizada
     * @return \Illuminate\Http\JsonResponse
     */
    public static function response422($errors = [], $message = 'Datos incorrectos')
    {
        return self::prepareResponse(422, $message, [
            'errors' => $errors
        ]);
    }

    /**
     * Ocurrió un error en el servidor durante el procesamiento de la petición.
     * 
     * @param string $message Mensaje descriptivo de la acción realizada
     * @return \Illuminate\Http\JsonResponse
     */
    public static function response500($extra = [], $message = 'Internal Server Error')
    {
        return self::prepareResponse(500, $message, $extra);
    }

    /**
     * Genera una respuesta en base a los parámetros especificados.
     *
     * @param int $code código de la respuesta
     * @param string $status descripción del estado 
     * 
     * Ej: `Success`, `Unauthorized`, etc
     * 
     * @param array $extra
     *
     * @return void
     */
    private static function prepareResponse($code, $status, $extra = [])
    {
        $response = ['message' => $status];
        if (count($extra) > 0) $response = array_merge($response, $extra);
        return self::json($response, $code);
    }


    /**
     * Retorna una respuesta en formato json.
     * 
     * @param array $response Respuesta JSON
     * @param int $status Código http, default 200
     * 
     * @return \Illuminate\Http\Response
     */
    public static function json($response, $status = 200, $headers = [])
    {
        return response()->json($response, $status, $headers);
    }

    /**
     * Undocumented function
     *
     * @param array|Collection $data
     * @param \Closure $formatter
     */
    public static function formatResponse($data = [], ?\Closure $formatter = null)
    {
        $formatted = [];

        if ($data === null) {
            return [];
        }

        // if (!$formatter)
        //     throw new \Exception('el parámetro $formatter es requerido');

        if (is_array($data) || $data instanceof Collection) { // $data es una lista
            foreach ($data as $item) {
                // if ($item instanceof \Eloquent) { // $data es un modelo
                //     $relations = $item->getRelations();
                //     $item = $item->toArray();
                //     $item = array_merge($item, $relations);
                // } else if ($data instanceof \stdClass) { // $data es un objeto
                //     $item = (array)$item;
                // }

                if (is_null($item) && $item instanceof Model) {
                    $formatted[] = $item->toArray();
                } else {
                    $formatted[] = $formatter($item);
                }
            }
        } else {
            // if ($data instanceof \Eloquent) { // $data es un modelo
            //     $data = $data->toArray();
            // } else if ($data instanceof \stdClass) { // $data es un objeto
            //     $data = (array)$data;
            // }

            if (is_null($data) && $data instanceof Model) {
                $formatted = $data->toArray();
            } else {
                $formatted = $formatter($data); // $data es un array
            }
        }

        return $formatted;
    }

    /**
     * Pagina un listado de recursos.
     *
     * @param Illuminate\Contracts\Pagination\LengthAwarePaginator $data Paginator
     * @param \Closure $formatter función que se encargara del formateo de recursos
     */
    public static function paginate(LengthAwarePaginator $data, \Closure $formatter = null, $extra = null)
    {
        $response = [
            'total' => $data->total(),
            'data' => self::formatResponse($data->items(), $formatter),

            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),

            'next_url' => $data->nextPageUrl(),
            'prev_url' => $data->previousPageUrl(),

            'last_page' => (int) $data->lastPage(),
            'last_url' => $data->url($data->lastPage()),
            'first_url' => $data->url(1),

            'filters' => request()->filtros,
        ];

        if ($extra && is_array($extra)) {
            $response['extra'] = $extra;
        }

        return self::response200($response);
    }

    /**
     * Retorna la excepción en formato JSON y los datos del usuario autenticado.
     *
     * @param \Exception|\Throwable $e
     * @return \Illuminate\Http\JsonResponse
     */
    public static function exceptionResponse($e, $onlyLog = false)
    {
        if ($e instanceof ErrorResponseInterface) {
            $status = $e->getStatus();
            $extra = $e->getExtra();
            $response = $e->getMessage() ?? $e->getResponse();

            if (request()->ajax()) {
                switch ($status) {
                    case 400:
                        return self::response400($extra, $response);
                    case 401:
                        return self::response401($extra, $response);
                    case 403:
                        return self::response403($extra, $response);
                    case 404:
                        return self::response404($extra, $response);
                    case 419:
                        return self::response400($extra, $response);
                    case 422:
                        return self::response422($extra, $response);
                    case 500:
                        return self::response500($extra, $response);
                }
            } else {
                return abort($status, $response);
            }
        }

        Log::error($e->getMessage() . $e->getTraceAsString(), ['url' => url()->current()]);

        if ($onlyLog) return;

        if (config('app.debug')) {
            $data = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace(),
            ];
        } else {
            $data = ['message' => $e->getMessage()];
        }

        return self::json($data, 500, [
            'Access-Control-Allow-Origin' => '*'
        ]);
    }
}
