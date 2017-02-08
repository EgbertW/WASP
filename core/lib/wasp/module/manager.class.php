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
namespace WASP\Module;

use WASP\Autoload\Resolve;
use WASP\Debug\Log;

/**
 * Find, initialize and manage modules.
 * The Manager::setup() function should be called as soon as the location of the modules
 * is known. Without calling setup, a call to getModules() will throw an exception.
 */
class Manager
{
    private static $logger = null;
    private static $initialized = false;
    private static $modules = array();

    /** 
     * Find and initialize installed modules in the module path
     *
     * @param $config WASP\Dictionary The configuration from which to obtain the module path
     */
    public static function setup($config)
    {
        if (self::$initialized)
            return;

        self::$logger = new Log('WASP.Module.Manager');

        $module_path = realpath($config->dget('site', 'module_path', WASP_ROOT . '/modules'));
        $modules = Resolve::listModules($module_path);

        foreach ($modules as $mod_name => $path)
        {
            self::$logger->info("WASP.Autoload.Resolve", "Found module {} in path {}", $mod_name, $path);
            Resolve::registerModule($mod_name, $path);
            self::$modules[$mod_name] = $path;

            // Create the module object, using the module implementation if available
            $load_class = 'WASP\\Module\\BasicModule';
            $mod_class = $mod_name . '\\Module';
            if (class_exists($mod_class))
            {
                if (is_subclass_of($mod_class, 'WASP\\Module\\Module'))
                    $load_class = $mod_class;
                else
                    self::$logger->warn('Module {} has class {} but it does not implement WASP\\Module\\Module', $mod_name, $mod_class);
            }

            self::$modules[$mod_name] = new $load_class($mod_name, $path);
        }
        self::$initialized = true;
    }

    /**
     * Return the list of found modules
     *
     * @return array A list of WASP\Module objects
     */
    public static function getModules()
    {
        if (!self::$initialized)
            throw new \RuntimeException("You need to initialize the module manager before using it");

        return array_values(self::$modules);
    }
}
