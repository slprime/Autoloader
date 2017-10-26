<?php

namespace slprime\Autoloader;

use RecursiveIterator, RecursiveIteratorIterator, RecursiveDirectoryIterator, FilesystemIterator;

class ClassMapGenerator {

    /** @var string  */
    protected $baseDIR = "";

    public function __construct(string $baseDIR = ""){
        $this->baseDIR = $baseDIR;
    }

    /**
     * Generate a class map file.
     *
     * @param string|FilesystemIterator|RecursiveIterator $dir Directories or a single path to search in
     * @param string $file The name of the class map file
     */
    public function dump ($dir, string $file): void {
        file_put_contents($file, sprintf('<?php return %s;', var_export($this->createMap($dir), true)));
    }

    /**
     * Iterate over all files in the given directory searching for classes.
     *
     * @param \Iterator|string $dir The directory to search in or an iterator
     *
     * @return array
     */
    public function createMap ($dir): array {

        if (is_string($dir)) {
            $dir = new RecursiveDirectoryIterator($dir,FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
        }

        if ($dir instanceof RecursiveIterator ) {
            $dir = new RecursiveIteratorIterator($dir);
        }

        $size = strlen($this->baseDIR);
        $classMap = [];

        foreach ($dir as $path) {

            if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $classes = $this->parse($path);

            if (empty($classes)) {
                continue;
            }

            if ($size && strpos($path, $this->baseDIR) === 0) {
                $path = substr($path, $size + 1);
            }

            foreach ($classes as $className) {

                if (isset($classMap[$className])) {
                    user_error("exist 2 identical class $className in $path and " . $classMap[$className], E_USER_WARNING);
                }

                $classMap[$className] = $path;
            }

        }

        return $classMap;
    }


    /**
     * Extract the classes in the given file.
     *
     * @param string $path The file to check
     * @return array The found classes and functions
     */
    public function parse (string $path): array {
        $tokens = token_get_all(file_get_contents($path));
        $i = -1;

        $classes = [];
        $namespace = "";
        $level = 0;

        while ($token = $this->goTo($tokens, $i, [T_NAMESPACE, "{", "}", T_DOUBLE_COLON, T_CLASS, T_INTERFACE, T_TRAIT])) {
            $key = is_array($token)? $token[0]: $token;

            if (!$level && $key === T_NAMESPACE) {
                $namespace = "";

                while (($token = $tokens[++$i]?? null) && (is_array($token) || $token !== ";" && $token !== "{")) {
                    if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {
                        $namespace .= $token[1];
                    }
                }

                if ($namespace !== "") {
                    $namespace .= "\\";
                }

                $level++;

                continue;
            }

            if (in_array($key, [T_CLASS, T_INTERFACE, T_TRAIT])) {

                $token = $this->goTo($tokens, $i, [T_STRING, T_IMPLEMENTS, T_EXTENDS, "{"]);

                if (is_array($token) && $token[0] === T_STRING && $token[1] !== "") {
                    $classes[] = $namespace . $token[1];
                }

                if (is_array($token) || $token !== "{") {
                    $this->goTo($tokens, $i, '{');
                }

                $this->skipBody($tokens, $i);

                continue;
            }

            if ($key === "{") {
                $level++;
                continue;
            }

            if ($level && $key === "}") {
                $level--;

                if (!$level) {
                    $namespace = "";
                }

                continue;
            }

            if ($key === T_DOUBLE_COLON) {
                $this->goTo($tokens, $i, ";");
                continue;
            }


        }

        return $classes;
    }

    private function skipBody (array &$tokens, int &$i): void {
        $level = 1;

        while ($level &&  isset($tokens[++$i])) {
            $token = &$tokens[$i];

            if (is_string($token)) {
                if ($token === '{') {
                    $level++;
                } elseif ($token === '}') {
                    $level--;
                }
            } elseif ($token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES || $token[0] === T_STRING_VARNAME) {
                $level++;
            }

        }

    }

    private function &goTo (array &$tokens, int &$i, $name) {

        if (is_array($name)) {
            while (isset($tokens[++$i]) && !in_array(is_array($tokens[$i])? $tokens[$i][0]: $tokens[$i], $name));
        } else {
            while (isset($tokens[++$i]) && (is_array($tokens[$i]) || $tokens[$i] !== $name));
        }

        return $tokens[$i];
    }

}