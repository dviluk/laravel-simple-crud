<?php

namespace Dviluk\LaravelSimpleCrud\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeRepository extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name} {--model=} {--localized}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new repository';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Repository';

    private function buildNamespaceFor($classPath, $namespace)
    {
        return $this->rootNamespace() . $namespace . '\\' . $classPath;
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        $model = $this->option('model');

        if (!$model) {
            $this->error('--model=ModelClass is required!');
            return false;
        }

        $model = $this->buildNamespaceFor($model, 'Models');
        if (!class_exists($model)) {
            $this->error('Model `' . $model . '` not exist!');
            return false;
        }

        $namespaceArr = explode('/', $this->getNameInput());
        $classNameIndex = count($namespaceArr) - 1;
        $className = $namespaceArr[$classNameIndex];
        $className = $this->buildFileName($className);
        $namespaceArr[$classNameIndex] = $className;
        $namespace = implode('\\', $namespaceArr);

        $name = $this->qualifyClass($namespace);

        $path = $this->getPath($name);

        // Next, We will check to see if the class already exists. If it does, we don't want
        // to create the class and overwrite the user's code. So, we will bail out so the
        // code is untouched. Otherwise, we will continue generating this class' files.
        if ((!$this->hasOption('force') ||
                !$this->option('force')) &&
            $this->alreadyExists($this->getNameInput())
        ) {
            $this->error($this->type . ' already exists!');

            return false;
        }

        // Next, we will generate the path to the location where this class' file should get
        // written. Then, we will build the class and make the proper replacements on the
        // stub files so that it gets the correctly formatted namespace and class name.
        $this->makeDirectory($path);

        $this->files->put($path, $this->sortImports($this->buildClass($name)));

        $this->info($this->type . ' created successfully.');
    }

    private function buildFileName(string $name)
    {
        if (strpos($name, 'Repository') === false) {
            $name .= 'Repository';
        }

        return $name;
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);
        $namespaceArr = explode('\\', $name);
        $className = $namespaceArr[count($namespaceArr) - 1];

        $namespace = implode('\\', array_slice($namespaceArr, 0, -1));
        $namespace = 'namespace ' . $namespace . ';';

        $stub = str_replace('RepositoryName', $className, $stub);
        $stub = str_replace('namespace App\\Repositories;', $namespace, $stub);

        $modelNamespace = $this->buildNamespaceFor($this->option('model'), 'Models');
        $modelNamespaceArr = explode('\\', $modelNamespace);

        $modelName = $modelNamespaceArr[count($modelNamespaceArr) - 1];

        $stub = str_replace('{ModelNamespace}', $modelNamespace, $stub);
        $stub = str_replace('{ModelName}', $modelName, $stub);

        return $stub;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        $useLocalized = $this->option('localized');

        $stub = 'repository';

        if ($useLocalized) {
            $stub = 'repository.localized';
        }

        return  __DIR__ . "/../../stubs/{$stub}.stub";
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Repositories';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the repository.'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['model', InputOption::VALUE_REQUIRED, 'La clase del modelo principal.'],
        ];
    }
}
