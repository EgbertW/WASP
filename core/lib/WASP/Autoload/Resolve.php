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
namespace WASP\Autoload;

use WASP\Debug\LoggerAwareStaticTrait;
use WASP\Debug;
use WASP\HttpError;
use WASP\Autoloader;
use WASP\Translate;
use WASP\Cache;
use WASP\Path;

/**
 * Resolve templates, routes, clases and assets from the core and modules.
 */
class Resolve
{
    use LoggerAwareStaticTrait;

    /** A list of installed modules */
    private static $modules;

    /** The cache of templates, assets, routes */
    private static $cache = null;

    /** Set to true after a call to findModules */
    private static $module_init = false;

    /**
     * Set up the resolve cache. Should be called just once, at the bottom of
     * this script, but repeated calling won't break anything.
     */
    public static function init()
    {
        if (self::$cache !== null)
            return;

        self::setLogger();
        self::$modules = array('core' => Path::$ROOT . '/core');
        self::$cache = new Cache('resolve');
    }

    /** 
     * Find installed modules in the module path
     * @param $module_path string Where to look for the modules
     */
    public static function listModules($module_path)
    {
        $dirs = glob($module_path . '/*');

        $modules = array('core' => Path::$ROOT . '/core');
        foreach ($dirs as $dir)
        {
            if (!is_dir($dir))
                continue;

            $has_lib = is_dir($dir . '/lib');
            $has_template = is_dir($dir . '/template');
            $has_app = is_dir($dir . '/app');
            $has_assets = is_dir($dir . '/assets');

            if (!($has_lib || $has_template || $has_app || $has_assets))
            {
                self::$logger->info("WASP.Autoload.Resolve", "Path {} does not contain any usable elements", $dir);
                continue;
            }
            
            $mod_name = basename($dir);
            $modules[$mod_name] = $dir;
        }
        return $modules;
    }

    /**
     * Add a module to the search path of the Resolver.
     *
     * @param $name string The name of the module. Just for logging purposes.
     * @param $path string The path of the module.
     */
    public static function registerModule($name, $path)
    {
        self::$modules[$name] = $path;
    }

    /**
     * Return the list of found modules
     */
    public static function getModules()
    {
        return array_keys(self::$modules);
    }

    /**
     * Resolve an controller / route.
     * @param $request string The incoming request
     * @return array An array containing:
     *               'path' => The file that best matches the route
     *               'route' => The part of the request that matches
     *               'module' => The source module for the controller
     *               'remainder' => Arguments object with the unmatched part of the request
     */
    public static function app($request)
    {
        $parts = array_filter(explode("/", $request));

        $routes = self::getRoutes();
        $ptr = $routes;

        $route = $routes['_'];
        $used_parts = array();
        foreach ($parts as $part)
        {
            if (!isset($ptr[$part]))
                break;

            $ptr = $ptr[$part];
            $route = $ptr['_'];
            $used_parts[] = $part;
        }

        if ($route === null)
        {
            self::$logger->info("Failed to resolve route for request to {0}", [$request]);
            return null;
        }

        $r = '/' . implode('/', $used_parts);
        $remain = array_slice($parts, count($used_parts));
        
        self::$logger->info("Resolved route for {0} to {1} (module: {2})", [$r, $route['path'], $route['module']]);
        return array("route" => $r, "path" => $route['path'], 'module' => $route['module'], 'remainder' => $remain);
    }
    
    /**
     * Find files and directories in a directory. The contents are filtered on
     * .php files and .wasp files.
     *
     * @param $dir string The directory to list
     * @param $recursive boolean Whether to also scan subdirectories
     * @return array The contents of the directory.
     */
    private static function listDir($dir, $recursive = true)
    {
        $contents = array();
        $subdirs = array();
        foreach (glob($dir . "/*") as $entry)
        {
            if (substr($entry, -4) === ".php" || substr($entry, -5) === ".wasp")
            {
                $contents[] = $entry;
            }
            elseif (is_dir($entry) && $recursive)
            {
                $subdirs = array_merge($subdirs, self::listDir($entry));
            }
        }

        // Sort the direct contents of the directory so that .wasp and index.php come first
        usort($contents, function ($a, $b) {
            $sla = strlen($a);
            $slb = strlen($b);
            
            // .wasp files come first
            $a_wasp = substr($a, -10) === "/.wasp";
            $b_wasp = substr($b, -10) === "/.wasp";
            if ($a_wasp !== $b_wasp)
                return $a_wasp ? -1 : 1;

            // index files come second
            $a_idx = substr($a, -10) === "/index.php";
            $b_idx = substr($b, -10) === "/index.php";
            if ($a_idx !== $b_idx)
                return $a_idx ? -1 : 1;

            // Finally, sort alphabetically
            return strncmp($a, $b);
        });

        // Add the contents of subdirectories to the direct contents
        return array_merge($contents, $subdirs);
    }

    /**
     * Get all routes available from all modules
     * @return array The available routes and the associated controller
     */
    public static function getRoutes()
    {
        $routes = self::$cache->get('routes');
        if (!empty($routes))
            return $routes;
        
        $routes = array();
        foreach (self::$modules as $module => $location)
        {
            $app_path = $location . '/app';
            
            $files = self::listDir($app_path);
            foreach ($files as $path)
            {
                $file = str_replace($app_path, "", $path);
                $parts = array_filter(explode("/", $file));
                $ptr = &$routes;

                $cnt = 0;
                $l = count($parts);
                foreach ($parts as $part)
                {
                    $last = $cnt === $l - 1;
                    if ($last)
                    {
                        if ($part === "index.php")
                        {
                            // Only store if empty - 
                            if (empty($ptr['_']) || substr($ptr['_']['path'], -9) !== "index.php")
                                $ptr['_'] = array('module' => $module, 'path' => $path);
                        }
                        elseif ($part === ".wasp")
                            continue; // TODO: NOT IMPLEMENTED
                        else
                        {
                            $app_name = substr($part, 0, -4);
                            if (!isset($ptr[$app_name]))
                                $ptr[$app_name] = array("_" => array('module' => $module, 'path' => $path));
                        }
                        break;
                    }
                
                    // Directory part
                    if (!isset($ptr[$part]))
                        $ptr[$part] = array("_" => null);

                    // Move the pointer deeper
                    $ptr = &$ptr[$part];
                }
            }
        }

        // Update the cache
        self::$cache->put('routes', $routes);
        return $routes;
    }

    /**
     * Locate a class in any of the modules. The class name will be
     * constructed by replacing all namespace separators by slashes
     * and appending .class.php or .php.
     * 
     */
    public static function class($class_name)
    {
        self::$logger->debug("Lookup up class {0}", [$class_name]);
        $resolved = self::$cache->get('class', $class_name);
        if ($resolved === null)
        {
            $path1 = str_replace('\\', '/',  $class_name) . ".class.php";
            $path2 = str_replace('\\', '/',  $class_name) . ".php";

            $resolved = self::resolve('lib', $path1, false, true);
            if (!$resolved)
                $resolved = self::resolve('lib', $path2, false, true);

            if ($resolved === null) // Store failure as false in the cache
                $resolved = false;

            self::$cache->put('class', $class_name, $resolved);
        }

        return $resolved ? $resolved : null;
    }

    /**
     * Resolve a template file. This method will traverse the installed
     * modules in reversed order. The files are ordered alphabetically, and
     * core always comes first.  By reversing the order, it becomes
     * possible to override templates by modules coming later.
     *
     * @param $template string The template identifier. 
     * @return string The location of a matching template.
     */
    public static function template($template)
    {
        if (substr($template, -4) != ".php")
            $template .= ".php";

        return self::resolve('template', $template, true);
    }

    /**
     * Resolve a asset file. This method will traverse the installed
     * modules in reversed order. The files are ordered alphabetically, and
     * core always comes first.  By reversing the order, it becomes
     * possible to override assets by modules coming later.
     *
     * @param $asset string The name of the asset file
     * @return string The location of a matching asset
     */
    public static function asset($asset)
    {
        return self::resolve('assets', $asset, true);
    }

    /**
     * Helper method that searches the core and modules for a specific type of file. 
     * The files are evaluated in alphabetical order, and core always comes first.
     *
     * @param $type string The type to find, template or asset
     * @param $file string The file to locate
     * @param $reverse boolean Whether to return the first matching or the last matching.
     * @param $case_insensitive boolean When this is true, all files will be compared lowercased
     * @return string A matching file. Null is returned if nothing was found.
     */
    private static function resolve($type, $file, $reverse = false, $case_insensitive = false)
    {
        if ($case_insensitive)
            $file = strtolower($file);

        $cached = self::$cache->get($type, $file);
        if ($cached === false)
            return null;

        if (!empty($cached))
        {
            if (file_exists($cached['path']) && is_readable($cached['path']))
            {
                self::$logger->debug("Resolved {0} {1} to path {2} (module: {3}) (cached)", [$type, $file, $cached['path'], $cached['module']]);
                return $cached['path'];
            }
            else
                self::$logger->error("Cached path for {0} {1} from module {2} cannot be read: {3}", [$type, $file, $cached['module'], $cached['path']]);
        }

        $path = null;
        $found_module = null;
        $mods = $reverse ? array_reverse(self::$modules) : self::$modules;

        // A glob pattern is composed to implement a case insensitive file search
        if ($case_insensitive)
        {
            $glob_pattern = "";
            // Create a character class [Aa] for each character in the string
            for ($i = 0; $i < strlen($file); ++$i)
            {
                $ch = substr($file, $i, 1); // lower case character, as strtlower was called above
                if ($ch !== '/')
                    $ch = '[' . strtoupper($ch) . $ch . ']';
                $glob_pattern .= $ch;
            }
        }

        foreach ($mods as $module => $location)
        {
            if ($case_insensitive)
            {
                $files = glob($location . '/' . $type . '/' . $glob_pattern);
                if (count($files) === 0)
                    continue;
                $path = reset($files);
            }
            else
            {
                self::$logger->debug("Trying path: {0}/{1}/{2}", [$location, $type, $file]);
                $path = $location . '/' . $type . '/' . $file;
            }

            if (file_exists($path) && is_readable($path))
            {
                $found_module = $module;
                break;
            }
        }

        if ($found_module !== null)
        {
            self::$logger->debug("Resolved {0} {1} to path {2} (module: {3})", [$type, $file, $path, $found_module]);
            self::$cache->put($type, $file, array("module" => $found_module, "path" => $path));
            return $path;
        }
        else
            self::$cache->put($type, $file, false);
    
        return null;
    }
}

// Set up the cache
Resolve::init();
