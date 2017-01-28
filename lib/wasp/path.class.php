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

class Path
{
    public static $ROOT;
    public static $TEMPLATE;
    public static $CONFIG;
    public static $APP;
    public static $FILES;
    public static $HTTP;
    public static $ASSETS;
    public static $JS;
    public static $CSS;
    public static $IMG;

    public static function setup()
    {
        define('WASP_CONFIG', WASP_ROOT . '/config');
        define('WASP_TEMPLATE', WASP_ROOT . '/template');
        define('WASP_APP', WASP_ROOT . '/app');
        define('WASP_FILES', WASP_ROOT . '/files');
        define('WASP_LIB', WASP_ROOT . '/lib');
        define('WASP_SYS', WASP_ROOT . '/sys');

        define('WASP_ASSETS', WASP_HTTP . '/assets');
        define('WASP_JS', WASP_ASSETS . '/js');
        define('WASP_CSS', WASP_ASSETS . '/css');
        define('WASP_IMG', WASP_ASSETS . '/img');

        self::$ROOT = WASP_ROOT;
        self::$HTTP = WASP_HTTP;
        self::$CONFIG = WASP_CONFIG;
        self::$TEMPLATE = WASP_TEMPLATE;
        self::$APP = WASP_APP;
        self::$FILES = WASP_FILES;

        self::$ASSETS = WASP_ASSETS;
        self::$JS = WASP_JS;
        self::$CSS = WASP_CSS;
        self::$IMG = WASP_IMG;
    }
}
