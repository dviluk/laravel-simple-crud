<?php

namespace Dviluk\LaravelSimpleCrud\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeCrud extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud 
        {name : Nombre del Modelo} 
        {plural-name? : Nombre del Controller y Repository, puede ser opcional si `name` esta en ingles, se convertirá en plural automaticamente} 
        {--controller-ns= : Namespace para el controlador, por default toma el que se especifica en el argumento `name`} 
        {--repository-ns= : Namespace para el repositorio, por default no se utiliza ninguno} 
        {--resource-ns= : Namespace para el resource} 
        {--no-admin : Crea el namespace dentro de `Admin\`} 
        {--model-ns= : Namespace para el modelo, por default no se utiliza ninguno}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new CRUD';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'CRUD';

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        $controllerNS = $this->option('controller-ns');
        $repositoryNS = $this->option('repository-ns');
        $modelNS = $this->option('model-ns');
        $resourceNS = $this->option('resource-ns');

        $withAdminControllerNamespace = !$this->option('no-admin');
        $controllersAdminNamespace = 'Admin\\';

        $name = $this->argument('name');
        // Genera el namespace completo
        $completeNamespace = $this->qualifyClass($name);
        // Se extrae el nombre de la clase principal
        $classNameFromNamespace = $this->getClassNameFromNamespace($completeNamespace);
        $className = Str::ucfirst($classNameFromNamespace);
        $completeNamespace = str_replace('\\' . $classNameFromNamespace, '\\' . $className, $completeNamespace);
        $namespace = explode('\\', $completeNamespace);
        array_pop($namespace);
        $namespace = implode('\\', $namespace);
        $classFolder = str_replace(['App\\', 'App'], '', $namespace);
        // Se lee el nombre plural
        $pluralName = $this->argument('plural-name') ?? Str::plural($className);
        $pluralName = Str::ucfirst($pluralName);

        if (!empty($controllerNS)) {
            $controllerNS .= '\\';
        } else if (!empty($classFolder)) {
            $controllerNS = $classFolder . '\\';
            if ($withAdminControllerNamespace) {
                $controllerNS = $controllersAdminNamespace . $controllerNS;
            }
        } else if ($withAdminControllerNamespace) {
            $controllerNS = $controllersAdminNamespace;
        } else {
            $controllerNS = '\\';
        }

        $classFolder = $classFolder ? $classFolder . '\\' : '';
        $repositoryNS = !empty($repositoryNS) ? $repositoryNS . '\\' :  $classFolder;
        $resourceNS = !empty($resourceNS) ? $resourceNS . '\\' : $classFolder;
        $modelNS = !empty($modelNS) ? $modelNS . '\\' : $classFolder;

        $modelName = $modelNS . $className;
        $this->call('make:model', ['name' => $modelName]);
        $repositoryName = $repositoryNS  . $pluralName . 'Repository';
        $this->call('make:repository', ['name' => $repositoryName, '--model' => $modelName]);
        $resourceName = $resourceNS . $className . 'Resource';
        $this->call('make:resource-for-crud', ['name' => $resourceName]);
        $this->call('make:crud-controller', ['name' => $controllerNS . $pluralName . 'Controller', '--resource' => $resourceName, '--repo' => $repositoryName]);

        $this->alert('Pasos para completar el crud');
        $this->info("1. Indicar en el model `{$modelNS}{$className}.php` el nombre de la tabla asociada y los campos que se llenaran de forma masiva (\$fillable).");
        $this->info("2. Agrega validaciones al controlador y especificar los datos que se tomaran del request.");
        $this->info("3. Registrar rutas del controlador {$controllerNS}{$pluralName}Controller.php");
        $this->info("4. Dar formato al `{$resourceNS}{$name}Resource.php.`");
    }

    private function getClassNameFromNamespace($name)
    {
        return str_replace($this->getNamespace($name) . '\\', '', $name);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return  __DIR__ . '/../../stubs/controller.crud.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'Nombre en singular escrito en CamelCase junto a su namespace.',],
            ['plural-name', InputArgument::OPTIONAL, 'Nombre en plural escrito en CamelCase',],
        ];
    }

    protected function getOptions()
    {
        return [
            ['controller-ns', InputOption::VALUE_OPTIONAL, 'IS REQUIRED',],
            ['repository-ns', InputOption::VALUE_OPTIONAL, 'IS REQUIRED',],
            ['resource-ns', InputOption::VALUE_OPTIONAL, 'IS REQUIRED',],
            ['model-ns', InputOption::VALUE_OPTIONAL, 'IS REQUIRED',],
        ];
    }
}
