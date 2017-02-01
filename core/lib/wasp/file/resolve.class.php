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
    use WASP\I18N;

    class Resolve
    {
        private static $modules = array('core' => WASP_ROOT . '/core');
        private static $cache = null;
        private static $cacheChanged = false;
        private static $logger;

        public static function init()
        {
            $cache_file = WASP_CACHE . '/' . 'resolve.cache';
            self::$cache = array();
            if (file_exists($cache_file))
            {
                self::loadCache();
            }
        }

        public static function setHook($config)
        {
            register_shutdown_function(array('WASP\\File\\Resolve', 'saveCache'));

            $timeout = $config->get('resolve', 'expire', 60); // Clear out cache every minute by default
            $st = isset(self::$cache['_timestamp']) ? self::$cache['_timestamp'] : 0;
        
            if (time() - $st > $timeout)
            {
                Debug\info("WASP.File.Resolve", "Cache is more than {} seconds old ({}), invalidating", $timeout, $st);
                self::$cache = array('_timestamp' => time());
            }
        }

        public static function findModules($config)
        {
            $module_path = realpath($config->get('site', 'module_path', WASP_ROOT . '/modules'));
            $dirs = glob($module_path . '/*');
            foreach ($dirs as $dir)
            {
                if (!is_dir($dir))
                    continue;

                $mod_name = basename($dir);
                $init_file = $dir . '/lib/' . $mod_name . '/module.class.php';
                $class_name = $mod_name . '\\Module';
                if (!file_exists($init_file) || !is_readable($init_file))
                {
                    Debug\info("WASP.File.Resolve", "Path {} does not contain init_file {}", $dir, $init_file);
                    continue;
                }

                require_once $init_file;
                if (!class_exists($class_name))
                {
                    Debug\debug("WASP.File.Resolve", "Path {} does have init file {} but it does not contain class {}", $dir, $init_file, $class_name);
                    continue;
                }

                if (!is_subclass_of($class_name, 'WASP\\Module'))
                {
                    Debug\debug("WASP.File.Resolve", "Init file {} contans class {} but it does not implement WASP\Module", $init_file, $class_name);
                    continue;
                }

                Debug\info("WASP.File.Resolve", "Found module {} in path {}", $mod_name, $dir);
                self::$modules[$mod_name] = $dir;
                call_user_func(array($class_name, "init"));

                I18N::setupTranslation($mod_name, $dir, $class_name);
            }
        }

        public static function loadCache()
        {
            $cache_file = WASP_CACHE . '/' . 'resolve.cache';
            if (!file_exists($cache_file))
                return;

            if (!is_readable($cache_file))
            {
                Debug\error("WASP.File.Resolve", "Cannot read cache from {$cache_file}");
                return;
            }

            $data = file_get_contents($cache_file);
            $cache = unserialize($data);
            if ($cache === false)
            {
                Debug\error("WASP.File.Resolve", "Cache file contains invalid data: {$cache_file} - removing");
                return;
            }

            Debug\info("WASP.File.Resolve", "Loaded {} bytes resolve cache data from: {}", strlen($data), $cache_file);
            self::$cache = $cache;
        }

        public static function saveCache()
        {
            if (!self::$cacheChanged)
                return;

            $cache_dir = WASP_CACHE;
            $cache_file = WASP_CACHE . '/' . 'resolve.cache';

            if (file_exists($cache_file && !is_writable($cache_file)))
            {
                Debug\error("WASP.File.Resolve", "Cannot write cache to {$cache_file}");
                return;
            }

            $data = serialize(self::$cache);
            file_put_contents($cache_file, $data);

            Debug\info("WASP.File.Resolve", "Saved {} bytes resolve cache data from: {}", strlen($data), $cache_file);
        }

        public static function purgeModuleFromCache($module)
        {
            if (self::$cache === null)
                return;

            $cnt = 0;
            foreach (self::$cache as $ctype => $data)
            {
                foreach ($data as $idx => $cached)
                {
                    if ($cached['module'] === $module)
                    {
                        unset(self::$cache[$ctype][$idx]);
                        ++$cnt;
                    }
                }
            }
            if ($cnt > 0)
                self::$cacheChanged = true;

            Debug\info("WASP.Util.Resolve", "Removed {} elements from cache for module {}", $cnt, $module);
        }

        public static function autoload($class_name)
        {
            // Check the cache first
            $class_name = strtolower($class_name);
            if (isset(self::$cache['class'][$class_name]))
            {
                $cached = self::$cache['class'][$class_name];
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
            {
                \WASP\Debug\info("WASP.File.Resolve", "Resolved class {} to {} (module: {})", $class_name, $path, $found_module);
                if (self::$cache !== null && $path !== null)
                {
                    self::$cache['class'][$class_name] = array('module' => $found_module, 'path' => $path);
                    self::$cacheChanged = true;
                }
            }
        }

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

        public static function getRoutes()
        {
            if (isset(self::$cache['routes']))
                return self::$cache['routes'];
            
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
            if (self::$cache !== null)
                self::$cache['routes'] = $routes;
            return $routes;
        }

        public static function template($template)
        {
            if (substr($template, -4) != ".php")
                $template .= ".php";

            return self::resolve('template', $template, true);
        }

        public static function asset($asset)
        {
            return self::resolve('assets', $asset, true);
        }

        private static function resolve($type, $file, $reverse = false)
        {
            if (isset(self::$cache[$type][$file]))
            {
                $cached = self::$cache[$type][$file];
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
                if (self::$cache !== null)
                {
                    self::$cache[$type][$file] = array("module" => $found_module, "path" => $path);
                    self::$cacheChanged = true;
                }
                return $path;
            }
        
            return null;
        }
    }

    Resolve::init();
    spl_autoload_register(array('WASP\\File\\Resolve', 'autoload'));
}
