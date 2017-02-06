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
namespace WASP\File
{
    use WASP\Debug;
    use WASP\HttpError;
    use WASP\Autoloader;
    use WASP\Translate;
    use WASP\Cache;

    /**
     * Resolve templates, routes, clases and assets from the core and modules.
     */
    class Resolve
    {
        /** A list of installed modules */
        private static $modules = array('core' => WASP_ROOT . '/core');

        /** The cache of templates, assets, routes */
        private static $cache = null;

        /** The logger instance */
        private static $logger;

        public static function init()
        {
            if (self::$cache !== null)
                return;
            
            $p = dirname(dirname(__FILE__)) . '/cache.class.php';
            require_once $p;

            self::$cache = new Cache('resolve');
        }

        /** 
         * Find installed modules in the module path
         * @param $config WASP\Config The configuration from which to obtain the module path
         */
        public static function findModules($config)
        {
            $module_path = realpath($config->get('site', 'module_path', WASP_ROOT . '/modules'));
            $dirs = glob($module_path . '/*');
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
                    Debug\info("WASP.File.Resolve", "Path {} does not contain any usable elements", $dir);
                    continue;
                }

                $mod_name = basename($dir);
                Debug\info("WASP.File.Resolve", "Found module {} in path {}", $mod_name, $dir);
                self::$modules[$mod_name] = $dir;

                // Check for an initialization module
                $init_file = $dir . '/lib/' . $mod_name . '/module.class.php';
                $class_name = $mod_name . '\\Module';
                if (!file_exists($init_file) || !is_readable($init_file))
                    continue;

                require_once $init_file;
                if (!class_exists($class_name) && !is_subclass_of($class_name, 'WASP\\Module'))
                    continue;

                call_user_func(array($class_name, "init"));
                Translate::setupTranslation($mod_name, $dir, $class_name);
            }
        }

        public static function getModules()
        {
            return array_keys(self::$modules);
        }

        /**
         * Remove a module from the cache
         * @param $module string The name of the module to uncache
         */
        public static function purgeModuleFromCache($module)
        {
            $cache = self::$cache->get();
            $cnt = 0;
            foreach (self::$cache as $ctype => $data)
            {
                foreach ($data as $idx => $cached)
                {
                    if (isset($cached['module']) && $cached['module'] === $module)
                    {
                        unset(self::$cache[$ctype][$idx]);
                        ++$cnt;
                    }
                }
            }

            if ($cnt > 0)
                $this->cache->replace($cache);

            Debug\info("WASP.Util.Resolve", "Removed {} elements from cache for module {}", $cnt, $module);
        }

        /**
         * The spl_autoloader that loads classes from the core and installed modules
         */
        public static function autoload($class_name)
        {
            // Check the cache first
            $cache = self::$cache->get('class');
            $class_name = strtolower($class_name);
            if (isset($cache[$class_name]))
            {
                $cached = $cache[$class_name];
                if (file_exists($cached['path']) && is_readable($cached['path']))
                {
                    Debug\debug("WASP.File.Resolve", "Including {}", $cached['path']);
                    require_once $cached['path'];
                    if (class_exists($class_name))
                    {
                        Debug\info("WASP.File.Resolve", "Resolved class {} to {} (module: {}) (cached)", $class_name, $cached['path'], $cached['module']);
                        return;
                    }
                }
            }

            $parts = explode('\\', $class_name);

            $path = null;
            $found_module = null;
            foreach (self::$modules as $module => $location)
            {
                $path = $location . '/lib/' . implode('/', $parts) . '.class.php';
                if (file_exists($path) && is_readable($path))
                {
                    require_once $path;
                    if (class_exists($class_name))
                    {
                        $found_module = $module;
                        break;
                    }
                }
                $path = null;
            }

            if (class_exists($class_name))
            { // SUCCESS!
                \WASP\Debug\info("WASP.File.Resolve", "Resolved class {} to {} (module: {})", $class_name, $path, $found_module);
                if ($path !== null)
                    self::$cache->put('class', $class_name, array('module' => $found_module, 'path' => $path));
            }
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
                Debug\info("WASP.Util.Resolve", "Failed to resolve route for request to {}", $request);
                return null;
            }

            $r = '/' . implode('/', $used_parts);
            $remain = array_slice($parts, count($used_parts));
            
            Debug\info("WASP.Util.Resolve", "Resolved route for {} to {} (module: {})", $r, $route['path'], $route['module']);
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
         * @return string A matching file
         */
        private static function resolve($type, $file, $reverse = false)
        {
            $cached = self::$cache->get($type, $file);
            if (!empty($cached))
            {
                if (file_exists($cached['path']) && is_readable($cached['path']))
                {
                    Debug\debug("WASP.File.Resolve", "Resolved {} {} to path {} (module: {}) (cached)", $type, $file, $cached['path'], $cached['module']);
                    return $cached['path'];
                }
                else
                    Debug\error("WASP.File.Resolve", "Cached path for {} {} from module {} cannot be read: {}", $type, $file, $cached['module'], $cached['path']);
            }

            $path = null;
            $found_module = null;
            $mods = $reverse ? array_reverse(self::$modules) : self::$modules;

            foreach ($mods as $module => $location)
            {
                $path = $location . '/' . $type . '/' . $file;
                Debug\error("RESOLVE", "Trying path: {}", $path);
                if (file_exists($path) && is_readable($path))
                {
                    Debug\error("RESOLVE", "Found path: {}", $path);
                    $found_module = $module;
                    break;
                }
            }

            if ($found_module !== null)
            {
                Debug\debug("WASP.File.Resolve", "Resolved {} {} to path {} (module: {})", $type, $file, $path, $found_module);
                self::$cache->put($type, $file, array("module" => $found_module, "path" => $path));
                return $path;
            }
        
            return null;
        }
    }

    // Set up the cache
    Resolve::init();

    // Set up the autoloader
    spl_autoload_register(array('WASP\\File\\Resolve', 'autoload'));
}
