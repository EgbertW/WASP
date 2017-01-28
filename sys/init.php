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

ini_set('default_charset', 'utf-8');
mb_internal_encoding('utf-8');

use WASP\Debug;

if (!defined('WASP_ROOT'))
{
    $root = dirname(realpath(dirname(__FILE__)));
    define('WASP_ROOT', $root);
    chdir(WASP_ROOT);
}

// Set up logging
ini_set('log_errors', '1');
ini_set('error_log', WASP_ROOT . '/log/error-php.log');

require_once WASP_ROOT . "/lib/wasp/debug/log.class.php";
if (isset($_SERVER['REQUEST_URI']))
    Debug\info("Sys.init", "*** Starting processing for {} request to {}", $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

require_once WASP_ROOT . "/sys/autoloader.php";
require_once WASP_ROOT . "/sys/functions.php";

WASP\Request::setErrorHandler();
WASP\Path::setup();
$config = WASP\Config::getConfig();

// Set up connection
$db = WASP\DB::get($config);
unset($db);
