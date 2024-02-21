<?php

namespace Dviluk\LaravelSimpleCrud;

use Closure;
use Dviluk\LaravelSimpleCrud\Utils\API\Error404;
use Dviluk\LaravelSimpleCrud\Utils\API\Error500;
use Dviluk\LaravelSimpleCrud\Utils\Arrays;
use Eloquent;
use Error;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Pagination\LengthAwarePaginator as PaginationLengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Repositorio base para manipular el CRUD de un modelo Laravel
 */
class Repository
{
    /**
     * Modelo principal de repositorio.
     *
     * En la clase hijo se debe declarar la clase del modelo
     * de la siguiente manera:
     *
     * ```php
     * protected $model = Model::class;
     * ```
     *
     * @var string
     */
    protected $model;

    /**
     * @var \Eloquent
     */
    protected $modelInstance;

    /**
     * Indica por que columna ordenar los resultados.
     *
     * ['column', 'asc'|'desc', ?'localized']
     *
     * (opcional) Si se indica `localized` se buscara la columna en la db según el idioma actual de
     * la aplicación.
     *
     * Ej. Se pasa la columna `product`, se ordenara por la columna `product` si la
     * aplicación esta en ingles.
     *
     * @var array
     */
    protected $orderBy = [];

    protected $defaultColumns = ['*'];

    protected $aggregateColumns = ['*'];

    private $handleSorting = 0;

    public function __construct()
    {
        $this->validateModel();

        // Se crea instancia del modelo
        $this->modelInstance = new $this->model;
    }

    /**
     * Prepara el query base.
     *
     * @param array $options
     *
     * - string|int `$options['find']` Elemento a buscar.
     * - string `$options['columnId']` Nombre de la columna donde buscara el valor de `$options['find']`.
     *
     * @return Builder
     * @throws Error
     */
    private function initQuery($options = [])
    {
        $query = $this->modelInstance::query();

        $tableName = $this->modelInstance->getTable();

        $columnId = $this->modelInstance->getKeyName();

        // Cuando se especifica la opción `orderBy` se omite la columna especificada en `$this->orderBy`
        if (array_key_exists('orderBy', $options)) {
            $orderBy = $options['orderBy'];

            if (!is_array($orderBy)) {
                throw new Error("`\$options['orderBy']` debe ser un arreglo");
            }

            if (count($orderBy) === 2 && !is_array($orderBy[0])) {
                $sortColumn = $orderBy[0];
                $sortDir = $orderBy[1];

                $columns = $this->modelInstance->getFillable();
                $columns[] = $this->modelInstance->getKeyName();

                if (isset(array_flip($columns)[$sortColumn])) {
                    $query->orderBy($tableName . '.' . $sortColumn, $sortDir);
                }
            } else {
                foreach ($orderBy as $order) {
                    if (!is_array($order) || count($order) !== 2) {
                        throw new Error("`\$options['orderBy']` no es valido");
                    }

                    $query->orderBy($order[0], $order[1]);
                }
            }
        } else if (is_array($this->orderBy) && count($this->orderBy) >= 2) {
            $localize = isset($this->orderBy[2]) && $this->orderBy[2] === 'localized';

            $column = $this->orderBy[0];

            if ($localize && method_exists($this, 'dbColumn')) {
                /** @var any $this */
                $column = $this->dbColumn($column);
            }

            $query->orderBy($column, $this->orderBy[1]);
        }

        if ($options instanceof Closure) {
            $options($query);
        } else if (is_array($options)) {
            // en caso de que `id` no sea la llave primaria o
            // se desea utilizar otra columna para la búsqueda
            if (array_key_exists('columnId', $options)) {
                $columnId = $options['columnId'];
            }

            if (array_key_exists('find', $options)) {
                $query->where($columnId, $options['find']);
            }

            if (array_key_exists('with', $options)) {
                $query->with($options['with']);
            }
        }

        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->model))) {
            $query->whereNull($tableName . '.deleted_at');
        }

        return $query;
    }

    /**
     * Permite encargarse de las opciones adicionales.
     *
     * @param Builder $builder
     * @param array $options
     * @return Builder
     */
    public function handleOptions(Builder $builder, array $options = []): Builder
    {
        return $builder;
    }

    /**
     * Modifica el query después de `$this->handleOptions($q, $options)`.
     *
     * @param Builder $builder
     * @return Builder
     */
    public function modifyQuery(Builder $builder): Builder
    {
        return $builder;
    }

    /**
     * Retornar el query configurado.
     *
     * @param array $options Las mismas opciones que en `Repository::initQuery($options)`
     * @return Builder
     * @throws Error
     */
    public function query(array $options = []): Builder
    {
        $query = $this->initQuery($options);

        $this->handleOptions($query, $options);
        $this->modifyQuery($query);

        if (
            // Se valida que el metodo `$this->handleTranslations()` exists
            $this->isResourceWithTranslations() && method_exists($this, 'handleTranslations')
            // Y tambien se valida que no se haya hecho un join de translations anteriormente
            && !$this->joined($query, 'translations')
        ) {
            /** @var mixed $this */
            $this->handleTranslations($query, $options);
        }

        return $query;
    }

    /**
     * Verifica si el query ya tiene el join de la tabla especificada.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $table
     * @return bool
     */
    protected function joined(Builder $query, $table)
    {
        $joins = $query->getQuery()->joins;
        if ($joins == null) {
            return false;
        }

        foreach ($joins as $join) {
            if ($join->table == $table) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consulta todos los registros.
     *
     * @param array $options Las mismas opciones que en `Repository::initQuery($options)`
     * @return Collection
     * @throws Error
     */
    public function all(array $options = [])
    {
        $query = $this->query($options);

        $columns = $options['columns'] ?? $this->defaultColumns;

        return $query->get($columns);
    }

    /**
     * Retorna los registros paginados.
     *
     * @param int $perPage
     * @param array $options
     * @return LengthAwarePaginator
     * @throws Error
     * @throws InvalidArgumentException
     */
    public function paginated($perPage = 10, $options = [])
    {
        $query = $this->query($options);

        $query->paginate();

        $columns = $options['columns'] ?? $this->defaultColumns;

        $pageName = 'page';

        $page = Paginator::resolveCurrentPage();

        $total = $query->toBase()->getCountForPagination($this->aggregateColumns);

        $results = $total ? $query->forPage($page, $perPage)->get($columns) : collect();

        return new PaginationLengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Retorna los números de pagina que se pueden aplicar.
     *
     * @return array
     */
    public function paginationOptions(): array
    {
        return [10, 25, 30, 50, 100];
    }

    /**
     * Busca un registro por ID.
     *
     * @param mixed $id
     * @param array $options Las mismas opciones que en `Repository::initQuery($options)`
     * @return null|Eloquent
     * @throws Error
     */
    public function find($id, array $options = [])
    {
        if ($id instanceof $this->model) {
            return $id;
        }

        $query = $this->query(array_merge($options, ['find' => $id]));

        $columns = $options['columns'] ?? $this->defaultColumns;

        return $query->first($columns);
    }

    /**
     * Busca un registro por la columna name.
     *
     * @param string $name
     * @param array $options
     * @return null|Eloquent
     * @throws \Error
     */
    public function findByName($name, array $options = [])
    {
        return $this->find($name, array_merge($options, ['columnId' => 'name']));
    }

    /**
     *
     * @param mixed $id
     * @param array $options
     * @return Eloquent
     * @throws Error
     * @throws Error404
     */
    public function findOrFail($id, array $options = [])
    {
        $item = $this->find($id, $options);

        if (!$item) {
            throw new Error404();
        }

        return $item;
    }

    /**
     * Valida si se puede crear un registro.
     *
     * @param array $data
     * @return void
     */
    public function canCreate(array $data, array $options = [])
    {
        //
    }

    /**
     * Crea un nuevo registro.
     *
     * @param array $data Contiene los campos a insertar en la tabla del modelo.
     * @return Eloquent
     * @throws Exception
     * @throws Throwable
     */
    public function create(array $data, array $options = [])
    {
        DB::beginTransaction();
        try {
            $validate = $options['validate'] ?? true;

            $prepareData = $options['prepareData'] ?? true;

            if ($prepareData) {
                $data = $this->prepareData($data, $options, 'create');
            }

            if ($validate) {
                $this->canCreate($data, $options);
            }

            $item = (new $this->model);

            $item->fill($data)->save();

            DB::commit();

            return $item;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Valida si se puede editar el registro.
     *
     * @param Eloquent $item
     * @return void
     */
    public function canUpdate($item, ?array $data = [], array $options = [])
    {
        //
    }

    /**
     * Actualiza un registro.
     *
     * @param mixed $id
     * @param array $data Contiene los campos a actualizar.
     * @param array $options
     * @return Eloquent
     * @throws Exception
     * @throws Throwable
     */
    public function update($id, array $data, array $options = [])
    {
        DB::beginTransaction();
        try {
            $data = $this->prepareData($data, $options, 'update');

            $item = $this->findOrFail($id, $options);

            $validate = $options['validate'] ?? true;

            if ($validate) {
                $this->canUpdate($item, $data, $options);
            }

            $item->fill($data)->save();

            DB::commit();

            return $item;
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Valida si se puede eliminar el registro.
     *
     * @param Eloquent $item
     * @return void
     */
    public function canDelete($item, array $options = [])
    {
        //
    }

    /**
     * Elimina un registro.
     *
     * @param mixed $id
     * @param array $options
     * @return Eloquent|null
     * @throws Exception
     * @throws Throwable
     */
    public function delete($id, array $options = [])
    {
        DB::beginTransaction();
        try {
            $shouldExists = $options['shouldExists'] ?? true;

            $item = $this->find($id, $options);

            // validar que el registro exista
            if (!$item) {
                if ($shouldExists) {
                    throw new Error404();
                } else {
                    return null;
                }
            }

            $validate = $options['validate'] ?? true;

            if ($validate) {
                $this->canDelete($item, $options);
            }

            $forceDelete = $options['force_delete'] ?? false;

            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($item)) && $forceDelete === true) {
                $item->forceDelete();
            } else {
                // eliminar el registro
                $item->delete();
            }

            DB::commit();

            return $item;
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->handleDeleteErrors($e);

            throw $e;
        }
    }

    protected function handleDeleteErrors(\Throwable $e)
    {
        if (strpos($e->getMessage(), 'Integrity constraint violation: 1451') !== false) {
            throw new Error500([], 'This item cannot be deleted, has data associated.');
        }
    }

    /**
     * Acciones básicas para una relación many-to-many.
     *
     * @param \Illuminate\Database\Eloquent\Relations\BelongsToMany $relation
     * @param string $action ATTACH | DETACH | DETACH_ALL | SYNC
     * @param array $data
     * @param array $options
     * @return array
     */
    protected function manyToManyActions(BelongsToMany $relation, string $action, array $data, array $options = [])
    {
        $action = strtoupper($action);

        if ($action === 'UPDATE') {
            if (array_key_exists('id', $data)) {
                $otherId = $data['id'];
                unset($data['id']);

                $relation->newPivotStatementForId($otherId)
                    ->update($data);

                return [$otherId];
            } else {
                $ids = [];
                $currentIds = array_flip($relation->pluck('id')->toArray());

                foreach ($data as $item) {
                    $otherId = $item['id'];
                    unset($item['id']);

                    if (empty($item)) {
                        continue;
                    }

                    if (array_key_exists($otherId, $currentIds)) {
                        $relation->newPivotStatementForId($otherId)
                            ->update($item);
                    } else {
                        $relation->attach($otherId, $item);
                    }

                    $ids[] = $otherId;
                }

                $idsToDelete = array_diff($currentIds, $ids);

                if (count($idsToDelete) > 0) {
                    $relation->detach($idsToDelete);
                }

                return $ids;
            }
        }

        $isAttachAction = $action === 'ATTACH';

        // Cuando es un AttachAction y no se tienen datos, se finaliza el proceso
        if ($isAttachAction && count($data) === 0) {
            return [];
        }

        $isSyncAction = $action === 'SYNC';

        $isInsertAction = $isAttachAction || $isSyncAction;

        $isArrayOfIds = $options['isArrayOfIds'] ?? true;
        $pivotKey = $options['pivotKey'] ?? 'id';

        // Cuando los datos no son un array de solo Ids, se le da formato de tal manera
        // cumpla con [$id => [$pivotData]]
        if (!$isArrayOfIds && $isInsertAction) {
            $data = Arrays::formatPivotData($data, $pivotKey);
        }

        // Cuando es un AttachAction, se omiten los items que ya existen
        if ($isAttachAction) {
            $currentIds = $relation->pluck('id')->toArray();

            if ($isArrayOfIds) {
                $data = Arrays::omitValues($data, $currentIds);
            } else {
                $data = Arrays::omitKeys($data, $currentIds);
            }
        }

        if ($isArrayOfIds) {
            $changes = $data;
        } else {
            $changes = array_keys($data);
        }

        switch ($action) {
            case 'ATTACH':
                $relation->attach($data);
                break;
            case 'DETACH':
                $relation->detach($data);
                break;
            case 'DETACH_ALL':
                $relation->detach();
                break;
            case 'SYNC':
                $changes = $relation->sync($data);
                break;
            default:
                throw new Error500([], $action . ': Action not found');
        }

        return $changes;
    }

    /**
     * Acción por default para modificar las relaciones de many-to-many.
     *
     * @param int|\Eloquent $id
     * @param string $action  ATTACH | DETACH | DETACH_ALL | SYNC
     * @param array $data
     * @param array $options
     * @return mixed
     * @throws \Error
     * @throws \App\Utils\API\Error404
     * @throws \InvalidArgumentException
     * @throws \App\Utils\API\Error500
     */
    public function defaultUpdateManyToManyRelation($id, string $action, array $data = [], array $options = [])
    {
        $relationName = $options['relationName'];

        $item = $this->findOrFail($id);

        $relation = $item->{$relationName}();

        $foreignKey = $options['foreignKey'] ?? $relation->getRelatedPivotKeyName();

        $changes = $this->manyToManyActions($relation, $action, $data, $options);

        if (isset($options['returnAttachedItems'])) {
            return $relation->wherePivotIn($foreignKey, $changes)->get();
        }

        return $item;
    }

    /**
     * Se encarga de validar que el `$this->model`
     * sea un string valido.
     *
     * @return void
     * @throws Error
     */
    private function validateModel()
    {
        if (!is_string($this->model)) {
            throw new \Error('`$this->model` not valid:' . $this->model);
        }
    }

    /**
     * Se encarga de preparar los datos que se utilizaran para insertar/actualizar.
     * @param array $data
     * @param array $options
     * @return array
     */
    protected function prepareData(array $data, array $options = [], string $method)
    {
        return $data;
    }

    /**
     * Valida si el recurso del modelo esta usando traducciones.
     *
     * @return bool
     */
    public function isResourceWithTranslations()
    {
        return in_array('App\Models\Traits\ModelWithTranslations', class_uses_recursive($this->model));
    }

    /**
     * Retorna una nueva instancia del modelo.
     *
     * @return \Eloquent
     */
    public function getModelInstance()
    {
        return new $this->model;
    }
}
