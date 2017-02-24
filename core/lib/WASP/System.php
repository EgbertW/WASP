<?php
/*
This is part of WASP, the Web Application Software Platform.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace WASP;

use WASP\Autoload\Autoloader;
use WASP\Autoload\Resolve;
use WASP\Http\Request;
use WASP\Http\Error as HttpError;
use WASP\IO\File;
use WASP\IO\Dir;
use WASP\Debug\{Logger, LoggerFactory, FileWriter};
use WASP\I18n\TranslateLogger;
use PSR\Log\LogLevel;

/**
 * @codeCoverageIgnore System is already executed before tests run
 */
class System
{
    private static $instance = null;

    private $bootstrapped = false;
    private $path;
    private $config;
    private $request;
    private $resolver;
    private $translate;
    private $template;

    public static function setup(Path $path, Dictionary $config)
    {
        if (self::$instance !== null)
            throw new \RuntimeException("Cannot initialize more than once");

        self::$instance = new System($path, $config);

        // Attach the error handler - all PHP Errors should be thrown as Exceptions
        self::$instance->setErrorHandler();
        return self::$instance;
    }

    public static function hasInstance()
    {
        return self::$instance !== null;
    }

    public static function getInstance()
    {
        if (self::$instance === null)
            throw new \RuntimeException("WASP has not been initialized yet");

        return self::$instance;
    }

    private function __construct(Path $path, Dictionary $config)
    {
        $this->path = $path;
        $this->config = $config;
        $this->bootstrap();
    }

    public function bootstrap()
    {
        if ($this->bootstrapped)
            throw new \RuntimeException("Cannot bootstrap more than once");

        // Set character set
        ini_set('default_charset', 'UTF-8');
        mb_internal_encoding('UTF-8');

        // Set up logging
        ini_set('log_errors', '1');

        $test = WASP_TEST === 1 ? "-test" : "";

        if (PHP_SAPI === 'cli')
            ini_set('error_log', $this->path->log . '/error-php-cli' . $test . '.log');
        else
            ini_set('error_log', $this->path->log . '/error-php' . $test . '.log');

        // Set default permissions for files and directories
        $this->setCreatePermissions();

        // Make sure permissions are adequate
        try
        {
            $this->path->checkPaths();
        }
        catch (PermissionError $e)
        {
            return $this->showPermissionError($e);
        }

        // Autoloader requires manual logger setup to avoid depending on external files
        LoggerFactory::setLoggerFactory(new LoggerFactory());
        Autoloader::setLogger(LoggerFactory::getLogger([Autoloader::class]));

        // Set up root logger
        $root_logger = Logger::getLogger();
        $root_logger->setLevel(LogLevel::DEBUG);
        $logfile = $this->path->log . '/wasp' . $test . '.log';
        $root_logger->addLogHandler(new FileWriter($logfile, LogLevel::INFO));

        //
        $this->setupTranslateLog();

        // Load the configuration file
        // Attach the dev logger when dev-mode is enabled
        if ($this->config->get('site', 'dev'))
        {
            $devlogger = new Debug\DevLogger(LogLevel::DEBUG);
            $root_logger->addLogHandler($devlogger);
        }

        // Log beginning of request handling
        if (isset($_SERVER['REQUEST_URI']))
        {
            Debug\debug(
                "WASP.System", 
                "*** Starting processing for {0} request to {1}", 
                [$_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']]
            );
        }

        // Change settings for CLI
        if (Request::cli())
        {
            $limit = (int)$this->config->dget('cli', 'memory_limit', 1024);
            ini_set('memory_limit', $limit . 'M');
            ini_set('max_execution_time', 0);
        }


        // Make sure xdebug does not overload var_dump
        ini_set('xdebug.overload_var_dump', 'off');

        // Save the cache if configured so
        Cache::setHook($this->config);

        // Find installed modules and initialize them
        Module\Manager::setup($this->path->modules, $this->get('resolver'));

        // Load utility functions
        Functions::load();

        // Do not run again
        $this->bootstrapped = true;
    }
    
    public static function __callStatic($func, $arguments)
    {
        $instance = self::getInstance();
        return $instance->__get($func);
    }

    public function __get($parameter)
    {
        return $this->get($parameter);
    }

    public function get($parameter)
    {
        switch ($parameter)
        {
            case "config":
                return $this->config;
            case "path":
                return $this->path;
            case "request":
                if ($this->request === null)
                {
                    $this->request = new Request(
                        $_GET,
                        $_POST,
                        $_COOKIE,
                        $_SERVER,
                        $this->config,
                        $this->path, 
                        $this->resolver
                    );
                }
                return $this->request;
            case "resolver":
                if ($this->resolver === null)
                    $this->resolver = new Resolve($this->path);
                return $this->resolver;
            case "translate":
                if ($this->translate === null)
                {
                    $this->translate = new I18n\Translate($this->path);
                    $this->translate->registerTextDomain('core', $this->path->core . '/language');
                }
                return $this->translate;
            case "template":
                if ($this->template === null)
                {
                    $r = $this->__get('request');
                    $this->template = $r->getTemplate();
                }
                return $this->template;
        }
        throw new \InvalidArgumentException("No such object: $parameter");
    }

    /**
     * Overwrite system objects, only to be used in unit tests
     */
    public function __set($parameter, $value)
    {
        if (!defined('WASP_TEST') || WASP_TEST !== 1)
            throw new \RuntimeException("Cannot modify system objects");
        
        if (!property_exists($this, $parameter))
            throw new \InvalidArgumentException("No such object: $parameter");

        $this->$parameter = $value;
    }

    private function showPermissionError(PermissionError $e)
    {
        $dev = $this->config->get('site', 'dev');

        if (PHP_SAPI !== "CLI")
        {
            http_response_code(500);
            header("Content-type: text/plain");
        }

        if ($dev)
        {
            $file = $e->path;
            echo "{$e->getMessage()}\n";
            echo "\n";
            echo Logger::str($e, $html);
        }
        else
        {
            echo "A permission error is preventing this page from displaying properly.";
        }
        die();
    }

    private function setCreatePermissions()
    {
        if ($this->config->has('io', 'group'))
            File::setFileGroup($this->config->get('io', 'group'));

        $file_mode = (int)$this->config->get('io', 'file_mode');
        if ($file_mode)
        {
            $of = $file_mode;
            $file_mode = octdec(sprintf("%04d", $file_mode));
            File::setFileMode($file_mode);
        }

        $dir_mode = (int)$this->config->get('io', 'dir_mode');
        if ($dir_mode)
        {
            $of = $dir_mode;
            $dir_mode = octdec(sprintf("%04d", $dir_mode));
            File::setDirMode($dir_mode);
        }
    }

    private function setupTranslateLog()
    {
        $logger = Logger::getLogger('WASP.I18n.Translator.Translator');
        $handler = new TranslateLogger($this->path->log . '/translate-%s-%s.pot');
        $logger->addLogHandler($handler);
    }

    public static function setErrorHandler()
    {
        set_error_handler(array("WASP\\System", "throwErrorException"), E_ALL | E_STRICT);
    }

    /**
     * Catch all PHP errors, notices and throw them as an exception instead.
     * @param int $errno Error number
     * @param string $errstr Error description
     * @param string $errfile The file where the error occured
     * @param int $errline The line where the error occured
     * @param mixed $errcontext Erro context
     */
    public static function throwErrorException($errno, $errstr, $errfile, $errline, $errcontext)
    {
        Debug\error(
            "WASP.System", 
            "PHP Error {0}: {1} on {2}({3})",
            [$errno, $errstr, $errfile, $errline]
        );
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

}
