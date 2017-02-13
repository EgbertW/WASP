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

ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

use WASP\Autoload\Autoloader;
use WASP\Debug;
use PSR\Log\LogLevel;
use WASP\Request;
use WASP\Path;

if (!defined('WASP_ROOT'))
{
    $root = dirname(realpath(dirname(__FILE__)));
    define('WASP_ROOT', $root);
    chdir(WASP_ROOT);

    define('WASP_CACHE', $root . '/var/cache');
    if (!file_exists(WASP_CACHE))
    {
        mkdir(WASP_CACHE);
        chmod(770, WASP_CACHE);
    }
}

// Set up logging
ini_set('log_errors', '1');

if (PHP_SAPI === 'cli')
    ini_set('error_log', WASP_ROOT . '/var/log/error-php-cli.log');
else
    ini_set('error_log', WASP_ROOT . '/var/log/error-php.log');

// Some general utility functions. Move to class?
require_once WASP_ROOT . "/sys/functions.php";

// Set up the autoloader
require_once WASP_ROOT . "/core/lib/WASP/Autoload/Autoloader.php";
Autoloader::registerNS('WASP', WASP_ROOT . '/core/lib/WASP');
Autoloader::registerNS('Psr\\Log', WASP_ROOT . '/core/lib/Psr/Log');

// Load required modules
echo "INIT LOGGER\n";
Debug\Log::setDefaultLevel(LogLevel::DEBUG);
if (isset($_SERVER['REQUEST_URI']))
    Debug\info("Sys.init", "*** Starting processing for {} request to {}", $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);


Request::setErrorHandler();
Path::setup();

// Load the configuration file
$config = WASP\Config::getConfig();

// Change settings for CLI
if (Request::cli())
{
    $limit = (int)$config->dget('cli', 'memory_limit', 1024);
    ini_set('memory_limit', $limit . 'M');
    ini_set('max_execution_time', 0);
}

// Save the cache if configured so
WASP\Cache::setHook($config);

// Find installed modules and initialize them
WASP\Module\Manager::setup($config);
