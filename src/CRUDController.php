<?php

namespace Dviluk\LaravelSimpleCrud;

use Dviluk\LaravelSimpleCrud\API;
use Dviluk\LaravelSimpleCrud\DataTableExport;
use Dviluk\LaravelSimpleCrud\Utils\API\Error500;
use Dviluk\LaravelSimpleCrud\Utils\HTMLToPDF;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CRUDController
{
    /**
     * Instancia del repositorio.
     *
     * @var \App\Repositories\Repository
     */
    protected $repo;

    /**
     * @var \App\Utils\Json\JsonResource
     */
    protected $resource;

    /**
     * Indica si se utilizaran los métodos localizados del repositorio.
     *
     * @var boolean
     */
    protected $localized = false;

    /**
     * Vistas
     *
     * @var array
     */
    protected $views = [];

    /**
     * @var \Eloquent
     */
    protected $tempItem;

    protected $dataTable = false;

    protected $paginated = false;

    public function __construct()
    {
        if (!is_string($this->repo)) {
            throw new Error500([], '$this->repo not valid');
        }

        if (!is_string($this->resource)) {
            throw new Error500([], '$this->resource not valid');
        }

        $this->repo = new $this->repo;

        // TODO: Cambiar `$this->localized` a privado para que no se puede cambiar desde
        // el controlador hijo.
        // Cuando `$this->localized` es verdadero se le da prioridad para soportar
        // los cambios anteriores
        if (!$this->localized && $this->repo->isResourceWithTranslations()) {
            $this->localized = $this->repo->isResourceWithTranslations();
        }
    }

    /**
     * Get data from update request.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return array
     */
    protected function updateValidator(Request $request, string $id): array
    {
        return $this->storeValidator($request);
    }

    /**
     * Get data from update request.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return array
     */
    protected function getUpdateData(Request $request, string $id): array
    {
        return $this->getStoreData($request);
    }

    /**
     * Validate store request input.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    protected function storeValidator(Request $request): array
    {
        return [];
    }

    /**
     * Get data from store request.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    protected function getStoreData(Request $request): array
    {
        return $request->all();
    }

    /**
     * Relaciones a cargar al consultar el listado.
     *
     * @return array
     */
    protected function indexRelations()
    {
        return [];
    }

    /**
     * Relaciones a cargar al consultar 1 elemento.
     *
     * @return array
     */
    protected function showRelations()
    {
        return [];
    }

    /**
     * Relaciones a cargar al consultar 1 elemento a editar.
     *
     * @return array
     */
    protected function editRelations()
    {
        return [];
    }

    /**
     * Retorna los datos que se le pasaran a la vista.
     *
     * @param string $view
     * @param \Eloquent $item Solo funciona en las vistas `edit` y `show`
     * @return array
     */
    protected function viewWithData(string $view, $item = null): array
    {
        return [];
    }

    /**
     * Retorna las opciones que se aplicaran en el método indicado.
     *
     * @param mixed $method
     * @return array
     */
    protected function options($method)
    {
        if ($method === 'index') {
            return ['params' => request()->all()];
        }

        return [];
    }

    /**
     * Permite ejecutar una acción antes de ejecutar el `$repo->create()` o `$repo->update()`.
     *
     * @param string $method
     * @param array $data
     * @param int|null $id Se para cuando $method = 'update'
     * @param \Eloquent|null $item Se para cuando $method = 'update'
     * @return void
     */
    protected function preAction(string $method, array $data, $id = null, $item = null): array
    {
        return $data;
    }

    /**
     * Permite ejecutar una acción después de ejecutar el `$repo->create()` o `$repo->update()`.
     *
     * @param string $method
     * @param array $data
     * @return void
     */
    protected function postAction(string $method, $item)
    {
        //
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $method = $this->localized ? 'allLocalized' : 'all';

        $queryOptions = $this->_queryOptions('index', [
            'with' => $this->indexRelations(),
        ]);

        $resourceOptions = $queryOptions['resourceOptions'] ?? [];

        if ($request->boolean('select')) {
            $items = $this->repo->{$method}($queryOptions);
            $resourceOptions['select'] = true;
            return new $this->resource($items, [], $resourceOptions);
        }

        $paginated = $queryOptions['paginated'] ?? $request->paginated ?? $this->paginated;
        $dataTable = $request->dataTable ?? $queryOptions['dataTable'] ?? $this->dataTable;
        $download = $request->download;

        if ($dataTable) {
            $search = $request->search['value'] ?? $request->search;

            if (array_key_exists('params', $queryOptions)) {
                $queryOptions['params']['search'] = $search;
            } else {
                $queryOptions['params'] = ['search' => $search];
            }

            $sortColumnIndex = $request->order[0]['column'] ?? null;
            if ($sortColumnIndex !== null) {
                $sortColumn = $request->columns[$sortColumnIndex];
                $sortColumnDir = $request->order[0]['dir'];
                $sortColumnName = $sortColumn['data'];
                $queryOptions['orderBy'] = [$sortColumnName, $sortColumnDir];
            }
        }

        if ($download) {
            $items = $this->repo->{$method}($queryOptions);
            $resourceOptions['download'] =  true;
            return $this->handleDownload($download, $items, $resourceOptions);
        } else if ($paginated) {
            $method = $this->localized  ? 'paginatedLocalized' : 'paginated';
            $perPage = $request->perPage ?? 15;

            if ($dataTable && !$request->ajax()) {
                $items = collect();
            } else {
                $items = $this->repo->{$method}($perPage, $queryOptions);
            }
        } else {
            $items = $this->repo->{$method}($queryOptions);
        }

        if ($request->ajax()) {
            return new $this->resource($items, [], $resourceOptions);
        }

        $withData = array_merge(compact('items'), $this->viewWithData('index'));

        return view($this->views['index'])->with($withData);
    }

    protected function handleDownload($download, $items, $resourceOptions)
    {
        $resource = new $this->resource($items, [], $resourceOptions);
        $itemsFormatted = $resource->toArray();

        $columns = method_exists($resource, 'getColumns')
            ? $resource->getColumns()
            :  $this->extractColumnsFromData($itemsFormatted, request()->columns);
        $labels = method_exists($resource, 'getLabels') ? $resource->getLabels() : null;
        $resourceName = method_exists($resource, 'getResourceName') ? $resource->getResourceName() : null;
        $columnsSettings = method_exists($resource, 'getColumnSettings') ? $resource->getColumnSettings() : null;
        $mapColumns = method_exists($resource, 'getMapColumns') ? $resource->getMapColumns() : null;
        $generalSettings = method_exists($resource, 'getGeneralSettings') ? $resource->getGeneralSettings() : [];
        $filename = Str::slug($resourceName);

        if (method_exists($this, 'generateDownloadName')) {
            /** @var any $this */
            $filename = $this->generateDownloadName($filename, ['resource_name' => $resourceName, 'download' => $download]);
        }

        if ($download === 'pdf') {
            return  HTMLToPDF::fromView(
                'print.datatable',
                [
                    'items' => $itemsFormatted,
                    'columns' => $columns,
                    'labels' => $labels,
                    'resourceName' => $resourceName,
                    'columnsSettings' => $columnsSettings,
                    'generalSettings' => $generalSettings,
                    'mapColumns' => $mapColumns,
                ],
                [
                    'orientation' => 'landscape',
                    'filename' => $filename,
                ]
            );
        } else if ($download === 'excel') {
            return Excel::download(new DataTableExport($itemsFormatted, $columns, $labels, $resourceName, $mapColumns), $filename . '.xlsx');
        }
    }

    protected function extractColumnsFromData($items, $columns)
    {
        if ($columns) {
            $cols = [];

            foreach ($columns as $col) {
                $columnName = $col['data'];

                if ($columnName !== null) {
                    $cols[] = $col['data'];
                }
            }

            return $cols;
        }

        $item = $items[0] ?? null;

        $keys = [];
        foreach ($item as $key => $value) {
            if (is_string($value) || is_numeric($value) || is_null($value)) {
                $keys[] = $value;
            }
        }

        return $keys;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return view($this->views['create'])
            ->with($this->viewWithData('create'));
    }

    /**
     * Store or update a resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed $id
     * @return \Illuminate\Http\Response
     */
    public function storeOrUpdate(Request $request)
    {
        $id = $request->id;
        $exists = $this->repo->getModelInstance()->find($id);

        if ($exists) {
            return $this->update($request, $id);
        } else {
            return $this->store($request);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $method = $this->localized  ? 'createLocalized' : 'create';

        $request->validate($this->prepareValidator($request));

        $data = $this->prepareGetData($request);

        $queryOptions = $this->_queryOptions('store');

        $resourceOptions = $queryOptions['resourceOptions'] ?? [];

        DB::beginTransaction();
        try {
            $data = $this->preAction('store', $data);

            $item = $this->repo->{$method}($data);

            if ($item instanceof Model) {
                $this->postAction('store', $item);

                $item->load($this->showRelations());

                DB::commit();

                if (method_exists($this, 'onStore')) {
                    /** @var any $this */
                    return $this->onStore($item);
                }

                return new $this->resource($item, [],  $resourceOptions);
            } else if ($item instanceof Collection) {
                DB::commit();

                if (method_exists($this, 'onStore')) {
                    /** @var any $this */
                    return $this->onStore($item);
                }

                return new $this->resource($item, [], $resourceOptions);
            }

            DB::commit();

            if (method_exists($this, 'onStore')) {
                /** @var any $this */
                return $this->onStore($item);
            }

            return API::response200([
                'data' => $item,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $method = $this->localized  ? 'findOrFailLocalized' : 'findOrFail';

        $queryOptions = $this->_queryOptions('show', [
            'with' => $this->showRelations(),
        ]);

        $resourceOptions = $queryOptions['resourceOptions'] ?? [];

        $item = $this->tempItem = $this->repo->{$method}($id, $queryOptions);

        if ($request->ajax()) {
            return new $this->resource($item, [], $resourceOptions);
        }

        $withData = array_merge(compact('item'), $this->viewWithData('show', $item));

        return view($this->views['show'])->with($withData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $queryOptions = $this->_queryOptions('edit', [
            'with' => $this->editRelations(),
        ]);

        $resourceOptions = $queryOptions['resourceOptions'] ?? [];


        if ($request->ajax()) {
            $item = $this->repo->findOrFail($id, $queryOptions);
            return new $this->resource($item, [], \array_merge(['editing' => true], $resourceOptions));
        } else {
            // Cuando se esta viendo la vista se consulta la version con traducciones
            $method = $this->localized  ? 'findOrFailLocalized' : 'findOrFail';
            $item = $this->tempItem = $this->repo->{$method}($id, $queryOptions);
        }

        $withData = array_merge(compact('item'), $this->viewWithData('edit', $item));

        return view($this->views['edit'])->with($withData);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $method = $this->localized  ? 'updateLocalized' : 'update';

        $request->validate($this->prepareValidator($request, $id));

        $data = $this->prepareGetData($request, $id);

        $queryOptions = $this->_queryOptions('update');

        $resourceOptions = $queryOptions['resourceOptions'] ?? [];

        DB::beginTransaction();
        try {
            $item = $this->repo->findOrFail($id);

            $data = $this->preAction('update', $data, $id, $item);

            $item = $this->repo->{$method}($item, $data);

            $this->postAction('update', $item);

            $item->load($this->showRelations());

            DB::commit();

            if (method_exists($this, 'onUpdate')) {
                /** @var any $this */
                return $this->onUpdate($item);
            }

            return new $this->resource($item, [], $resourceOptions);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $queryOptions = $this->_queryOptions('destroy');

        $item = $this->repo->delete($id, $queryOptions);

        if (method_exists($this, 'onDestroy')) {
            /** @var any $this */
            return $this->onDestroy($item);
        }

        return API::response200();
    }

    /**
     * Retorna las opciones que se pasaran a la consulta.
     *
     * @param mixed $method
     * @param array $attach
     * @return array
     */
    private function _queryOptions($method, $attach = [])
    {
        return array_merge_recursive($this->options($method), $attach);
    }

    /**
     * Valida si el recurso del modelo esta usando traducciones.
     *
     * @return bool
     */
    private function isResourceWithTranslations()
    {
        // Para compatibilidad con la implementacion anterior de traducciones
        // se usa como fallback $this->localized si isResourceWithTranslations() retorna false
        if ($this->repo->isResourceWithTranslations()) {
            return true;
        }

        return $this->localized;
    }

    /**
     * Prepara el `$this->storeValidator()` y `$this->updateValidator()`.
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed|null
     * @return array
     */
    private function prepareValidator(Request $request, $id = null)
    {
        if ($this->repo->isResourceWithTranslations()) {
            $model = $this->repo->getModelInstance();
            $columnToTranslate = $model->mapLocalizedInputsToPrimaryTable();

            $rules = [];

            if ($id) {
                $rules = $this->updateValidator($request, $id);
            } else {
                $rules = $this->storeValidator($request);
            }

            $otherColumns = [];

            foreach ($rules as $column => $rule) {
                if (array_key_exists($column, $columnToTranslate)) {
                    $columnToTranslate[$column] = $rule;
                } else {
                    $otherColumns[$column] = $rule;
                }
            }

            if (method_exists($this, 'combineLocalizedInputRules')) {
                /** @var any $this */
                $rules = $request->only($this->combineLocalizedInputRules($columnToTranslate, $otherColumns));
            } else {
                $rules = $otherColumns;
            }

            return $rules;
        }

        if ($id) {
            return $this->updateValidator($request, $id);
        } else {
            return $this->storeValidator($request);
        }
    }

    /**
     * Se encarga de preparar `$this->getStoreData()` y `$this->getUpdateData()`.
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed|null $id
     * @return array
     */
    private function prepareGetData(Request $request, $id = null)
    {
        if ($this->repo->isResourceWithTranslations()) {
            $model = $this->repo->getModelInstance();
            $columnToTranslate = $model->mapLocalizedInputsToPrimaryTable();

            $inputs = [];

            if ($id) {
                $inputs = $this->getUpdateData($request, $id);
            } else {
                $inputs = $this->getStoreData($request);
            }

            $otherInputs = [];
            $inputsToTranslate = [];

            foreach ($inputs as $input) {
                if (array_key_exists($input, $columnToTranslate)) {
                    $inputsToTranslate[] = $input;
                } else {
                    $otherInputs[] = $input;
                }
            }

            if (method_exists($this, 'combineLocalizedInputNames')) {
                /** @var any $this */
                $inputs = $request->only($this->combineLocalizedInputNames($inputsToTranslate, $otherInputs));
            } else {
                $inputs = $otherInputs;
            }

            return $inputs;
        }

        if ($id) {
            return $this->getUpdateData($request, $id);
        } else {
            return $this->getStoreData($request);
        }
    }
}
