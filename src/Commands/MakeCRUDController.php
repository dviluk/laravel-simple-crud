<?php

namespace Dviluk\LaravelSimpleCrud\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeCRUDController extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud-controller {name} {--repo=} {--resource=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new CRUD controller';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'CRUD';

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
        $repo = $this->option('repo');
        $resource = $this->option('resource');

        if (!$repo) {
            $this->error('--repo=RepoClass is required!');
            return false;
        }

        if (!class_exists($this->buildNamespaceFor($repo, 'Repositories'))) {
            $this->error('Repository `' . $repo . '` not exist!');
            return false;
        }

        if (!$resource) {
            $this->error('--resource=ResourceClass is required!');
            return false;
        }

        if (!class_exists($this->buildNamespaceFor($resource, 'Http\\Resources'))) {
            $this->error('Resource `' . $resource . '` not exist!');
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
        if (strpos($name, 'Controller') === false) {
            $name .= 'Controller';
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

        $repoNamespace = $this->buildNamespaceFor($this->option('repo'), 'Repositories');
        $repoNamespaceArr = explode('\\', $repoNamespace);
        $repoName = $repoNamespaceArr[count($repoNamespaceArr) - 1];

        $resourceNamespace = $this->buildNamespaceFor($this->option('resource'), 'Http\Resources');
        $resourceNamespaceArr = explode('\\', $resourceNamespace);
        $resourceName = $resourceNamespaceArr[count($resourceNamespaceArr) - 1];

        return strtr($stub, [
            '{{ class }}' => $className,
            '{{ namespace }}' => $namespace,
            '{{ repoClass }}' => $repoName,
            '{{ repoNamespace }}' => $repoNamespace,
            '{{ resourceClass }}' => $resourceName,
            '{{ resourceNamespace }}' => $resourceNamespace,
            '{{ rootNamespace }}' => $this->rootNamespace(),
        ]);
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
        return $rootNamespace . '\Http\Controllers';
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
            [
                'repo', InputOption::VALUE_REQUIRED, 'La clase del repositorio.',
                'resource', InputOption::VALUE_REQUIRED, 'La clase del resource.',
            ],
        ];
    }
}
