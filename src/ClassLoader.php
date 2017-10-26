<?php

namespace slprime\Autoloader;

class ClassLoader {

    protected $fallbackDirs = [];
    protected $namespaces = [];
    protected $classMap = [];
    protected $prefixes = [];

    protected static $register = [];

    public function __construct($config = []){

        if (is_array($config)) {
            $this->addConfig($config);
        } elseif (is_string($config) && $config !== "") {
            $this->includeConfigFile($config);
        }

    }

    /**
     * Register loader with SPL autoloader stack.
     *
     * @return void
     */
    public function register(){

        if (empty(static::$register)) {
            spl_autoload_register([$this, 'loadClass'], false);
        }

        if (empty(static::$register) || !in_array($this, static::$register)) {
            static::$register[] = $this;
        }

    }

    /**
     * UnRegister loader with SPL autoloader stack.
     *
     * @return void
     */
    public function unregister(){

        if (($id = array_search($this, static::$register, true)) !== false) {
            unset(static::$register[$id]);
        }

        if (empty(static::$register)) {
            spl_autoload_unregister([$this, 'loadClass']);
        }

    }

    //----------------------------------------------------------------------------------------------------

    /**
     * Adds a base directory for a namespace prefix.
     *
     * @param string $namespace The namespace prefix.
     * @param string|array $baseDir A base directory for class files in the namespace.
     * @throws \Exception
     * @return void
     */
    public function addNamespace (string $namespace, $baseDir) {

        // normalize namespace prefix
        $namespace = trim($namespace, "\\") . "\\";
        $baseDir = is_array($baseDir)? $baseDir: [$baseDir];

        foreach ($baseDir as $directory) {
            // normalize the base directory with a trailing separator
            $directory = $this->findAlias($directory);

            $this->namespaces[] = [$namespace, $directory];
        }

    }

    /**
     * @param string $className The class name
     * @param string $baseDir A base directory for class files in the namespace.
     * @return void
     */
    public function addClassMap (string $className, $baseDir) {

        // normalize prefix
        $className = ltrim($className,"\\");

        // normalize the base directory with a trailing separator
        $baseDir = $this->findAlias($baseDir);

        $this->classMap[$className] = $baseDir;
    }

    /**
     * @param string $prefix
     * @param string|array $baseDir
     */
    public function addPrefix (string $prefix, $baseDir) {

        // normalize prefix
        $prefix = ltrim($prefix,"\\");
        $baseDir = is_array($baseDir)? $baseDir: [$baseDir];

        foreach ($baseDir as $directory) {
            // normalize the base directory with a trailing separator
            $directory = $this->findAlias($directory);

            if ($prefix === "") {
                $this->fallbackDirs[] = $directory;
            } else {
                $this->prefixes[] = [$prefix, $directory];
            }

        }

    }

    //----------------------------------------------------------------------------------------------------

    /**
     * @param array $files
     */
    public function includeConfigFiles (array $files) {
        foreach ($files as $file) {
            $this->includeConfigFile($file);
        }
    }

    /**
     * @param array $config
     */
    public function addConfig (array $config) {

        if (!empty($config['namespaces'])) {
            $this->registerNamespaces($config['namespaces']);
        }

        if (!empty($config['prefixes'])) {
            $this->registerPrefixes($config['prefixes']);
        }

        if (!empty($config['classMap'])) {
            $this->registerClassMaps($config['classMap']);
        }

    }

    //----------------------------------------------------------------------------------------------------

    public function includeConfigFile (string $file) {
        if (file_exists($file) && is_file($file)) {
            $this->addConfig(include($file));
        }
    }

    public function registerNamespaces (array $namespaces) {
        foreach ($namespaces as $prefix => $path) {
            $this->addNamespace($prefix, $path);
        }
    }

    public function registerClassMaps (array $classMaps) {
        foreach ($classMaps as $className => $path) {
            $this->addClassMap($className, $path);
        }
    }

    public function registerPrefixes (array $prefixes) {
        foreach ($prefixes as $prefix => $path) {
            $this->addPrefix($prefix, $path);
        }
    }

    //----------------------------------------------------------------------------------------------------

    /**
     * @param string $class
     * @return bool
     */
    public function loadClass (string $class): bool {
        $class = ltrim($class, "\\");

        /**
         * @var string $file
         */
        if ($file = static::findFile($class)) {
            require $file;
            return true;
        }

        return false;
    }

    /**
     * replace alias in path directory
     * @param string $path
     * @return string
     * @throws \Exception
     */
    protected function findAlias (string $path) {
        return rtrim($path, "/");
    }

    //----------------------------------------------------------------------------------------------------

    /**
     * @param string $class
     * @return null|string
     */
    public static function findFile (string $class) {
        $class = ltrim($class, "\\");

        /** @var static $static */
        foreach (static::$register as $static) {

            /* classMap */
            if (isset($static->classMap[$class]) && file_exists($static->classMap[$class])) {
                return $static->classMap[$class];
            }

            /* namespaces */
            foreach ($static->namespaces as list($namespace, $baseDir)) {
                if (0 === strpos($class, $namespace)) {
                    $classWithoutPrefix = substr($class, strlen($namespace));
                    $file = $baseDir . "/" . str_replace("\\", "/", $classWithoutPrefix) . '.php';
                    if (file_exists($file)) {
                        return $file;
                    }
                }
            }

            /* prefixes */
            foreach ($static->prefixes as list($prefix, $baseDir)) {
                if (0 === strpos($class, $prefix)) {
                    $classWithoutPrefix = substr($class, strlen($prefix));
                    $file = $baseDir . "/" . str_replace('_', "/", $classWithoutPrefix) . '.php';
                    if (file_exists($file)) {
                        return $file;
                    }
                }
            }

            /* fallbackDirs */
            foreach ($static->fallbackDirs as $baseDir) {
                $file = $baseDir . "/" . str_replace('_', "/", $class) . '.php';
                if (file_exists($file)) {
                    return $file;
                }
            }

        }

        return null;
    }

    //----------------------------------------------------------------------------------------------------

}