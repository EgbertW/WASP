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

namespace Sys;

use Debug;

class AutoLoader
{
    public static $classes = null;
    public static $templates = null;
    public static $apps = null;
    public static $assets = null;

    public static function init()
    {
        if (!defined('WASP_LIB') || !class_exists("WASP\\Config"))
            return;

        self::$classes = array(WASP_LIB);
        self::$templates = array(WASP_TEMPLATE);
        self::$apps = array(WASP_APP);
        self::$assets = array(WASP_ASSETS);
        
        $config = \WASP\Config::load();
        $lib_path = $config->get('path', 'lib');
        $app_path = $config->get('path', 'app');
        $tpl_path = $config->get('path', 'template');
        $assets_path = $config->get('path', 'assets');

        if (!empty($lib_path))
        {
            $lib_path = explode(";", $lib_path);
            $lib_path = array_filter($lib_path);

            foreach ($lib_path as $path)
            {
                $cp = realpath($path);
                if ($cp === false)
                {
                    Debug\warn("Sys.autoload", "Lib path $path from config does not exist");
                    continue;
                }

                if (!in_array($cp, self::$classes))
                    self::$classes[] = $cp;
            }
        }

        if (!empty($app_path))
        {
            $app_path = explode(";", $app_path);
            $app_path = array_filter($app_path);

            foreach ($app_path as $path)
            {
                $cp = realpath($path);

                if ($cp === false)
                {
                    Debug\warn("Sys.autoload", "App path $path from config does not exist");
                    continue;
                }

                if (!in_array($cp, self::$apps))
                    array_unshift(self::$apps, $cp);
            }
        }

        if (!empty($tpl_path))
        {
            $tpl_path = explode(";", $tpl_path);
            $tpl_path = array_filter($tpl_path);

            foreach ($tpl_path as $path)
            {
                $cp = realpath($path);

                if ($cp === false)
                {
                    Debug\warn("Sys.autoload", "Template path $path from config does not exist");
                    continue;
                }

                if (!in_array($cp, self::$templates))
                    array_unshift(self::$templates, $cp);
            }
        }

        if (!empty($assets_path))
        {
            $assets_path = explode(";", $assets_path);
            $assets_path = array_filter($assets_path);

            foreach ($assets_path as $path)
            {
                $cp = realpath($path);

                if ($cp === false)
                {
                    Debug\warn("Sys.autoload", "Assets path $path from config does not exist");
                    continue;
                }

                if (!in_array($cp, self::$assets))
                    array_unshift(self::$assets, $cp);
            }
        }
    }
}

function autoload($class_name)
{
    if (Autoloader::$classes === null)
        Autoloader::init();

    $paths = AutoLoader::$classes;
    if ($paths === null)
        $paths = array(WASP_ROOT . '/lib');

    foreach ($paths as $base_path)
    {
        $lc = strtolower($class_name);
        $parts = explode("\\", $lc);
        
        $path = $base_path . '/' . implode('/', $parts) . '.class.php';
        if (file_exists($path))
        {
            Debug\debug('Sys.autoload', 'Loading file {}', $path);
            require_once $path;
            return;
        }
    }
}

spl_autoload_register('Sys\\autoload');
