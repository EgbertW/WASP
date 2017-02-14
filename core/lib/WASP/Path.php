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
    public static $CONFIG;
    public static $SYS;
    public static $VAR;
    public static $CACHE;

    public static $HTTP;
    public static $ASSETS;
    public static $JS;
    public static $CSS;
    public static $IMG;

    /**
     * Fill the variables with proper (default) values, called by Bootstrap
     * 
     * @param string $root The WASP root directory
     * @param string $webroot The webroot, where the index.php resides. If omitted,
     *                        it defaults to WASP/http
     */
    public static function setup(string $root, string $webroot = null)
    {
        self::$ROOT = realpath($root);
        if (self::$ROOT === false)
            throw new \RuntimeException("Root does not exist");

        self::$CONFIG = self::$ROOT . '/config';
        self::$SYS = self::$ROOT . '/sys';
        self::$VAR = self::$ROOT . '/var';
        self::$CACHE = self::$VAR . '/cache';

        if (empty($webroot))
            self::$HTTP = self::$ROOT . '/http';
        else
            self::$HTTP = realpath($webroot);
        
        if (self::$HTTP === false || !is_dir(self::$HTTP))
            throw new \RuntimeException("Webroot does not exist");

        self::$ASSETS = self::$HTTP . '/assets';
        self::$JS = self::$ASSETS . '/js';
        self::$CSS = self::$ASSETS . '/css';
        self::$IMG = self::$ASSETS . '/img';
    }
}
