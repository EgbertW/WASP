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
use WASP\Debug\{Logger, LoggerFactory};
use PSR\Log\LogLevel;

/**
 * @codeCoverageIgnore Bootstrap is already executed before tests run
 */
class Bootstrap
{
    private static $instance = null;

    public static function bootstrap(string $root)
    {
        if (self::$instance === null)
        {
            self::$instance = new Bootstrap();
            self::$instance->execute($root);
        }
        else
            throw new \RuntimeException("Cannot bootstrap more than once");

        return self::$instance;
    }

    public function execute(string $root)
    {
        ini_set('default_charset', 'UTF-8');
        mb_internal_encoding('UTF-8');

        // Determine the root directory
        $root_directory = 
            dirname( // WASP
                dirname( // core
                    dirname( // lib
                        dirname( // WASP
                            realpath(__FILE__) // Bootstrap.php
                        )
                    )
                )
            );

        // Set up the path configuration
        Path::setup($root_directory);

        // Change directory to the WASP root
        chdir(Path::$ROOT);

        // Set up logging
        ini_set('log_errors', '1');

        $test = WASP_TEST === 1 ? "-test" : "";

        if (PHP_SAPI === 'cli')
            ini_set('error_log', Path::$ROOT . '/var/log/error-php-cli' . $test . '.log');
        else
            ini_set('error_log', Path::$ROOT . '/var/log/error-php' . $test . '.log');

        // Some general utility functions. Move to class?
        require_once Path::$ROOT . "/sys/functions.php";

        // Add the Psr namespace to the autoloader
        Autoloader::registerNS('Psr\\Log', Path::$ROOT . '/core/lib/Psr/Log');

        // Autoloader requires manual logger setup to avoid depending on external files
        Debug\LoggerFactory::setLoggerFactory(new Debug\LoggerFactory());
        Autoloader::setLogger(Debug\LoggerFactory::getLogger([Autoloader::class]));

        // Set up root logger
        $root_logger = Debug\Logger::getLogger();
        $root_logger->setLevel(LogLevel::DEBUG);
        $root_logger->addLogHandler(array(Logger::class, 'writeFile'));

        // Log beginning of request handling
        if (isset($_SERVER['REQUEST_URI']))
        {
            Debug\info(
                "Sys.init", 
                "*** Starting processing for {0} request to {1}", 
                [$_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']]
            );
        }

        // Attach the error handler
        Request::setErrorHandler();

        // Load the configuration file
        $config = Config::getConfig();

        // Change settings for CLI
        if (Request::cli())
        {
            $limit = (int)$config->dget('cli', 'memory_limit', 1024);
            ini_set('memory_limit', $limit . 'M');
            ini_set('max_execution_time', 0);
        }

        // Save the cache if configured so
        Cache::setHook($config);

        // Find installed modules and initialize them
        Module\Manager::setup($config);
    }
}
