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


    class Resolve
    {
        private static $modules = array('core' => WASP_ROOT . '/core');
        private static $cache = null;
        private static $cacheChanged = false;
        private static $logger;

        public static function initCache()
        {
            $cache_file = WASP_CACHE . '/' . 'resolve.cache';
            self::$cache = array();
            if (file_exists($cache_file))
            {
                self::loadCache();
            }
            elseif (file_exists($cache_file))
            {
                unlink($cache_file);
            }
        }

        public static function setHook($config)
        {
            if (!$config->get('site', 'dev', false))
            {
                register_shutdown_function(array('WASP\\File\\Resolve', 'saveCache'));
            }
            else
            {
                $cache_file = WASP_CACHE . '/' . 'resolve.cache';
                if (file_exists($cache_file) && is_writable($cache_file))
                {
                    unlink($cache_file);
                    Debug\info("WASP.File.Resolve", "Removing {} because caching is disabled", $cache_file);
                }
                self::$cache = null;
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

        private static function listDir($dir)
        {
            $contents = array();
            foreach (glob($dir . "/*") as $entry)
            {
                if (substr($entry, -4) === ".php")
                {
                    $contents[] = $entry;
                }
                elseif (is_dir($entry))
                {
                    $contents = array_merge($contents, self::listDir($entry));
                }
            }

            usort($contents, function ($a, $b) {
                $sla = strlen($a);
                $slb = strlen($b);
                if ($sla !== $slb)
                    return $sla - $slb;
                return strcmp($a, $b);
            });
            return $contents;
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

            if (isset(self::$cache['template'][$template]))
            {
                $cached = self::$cache['template'][$template];
                if (file_exists($cached['path']) && is_readable($cached['path']))
                {
                    Debug\debug("WASP.File.Resolve", "Resolved template {} to path {} (module: {}) (cached)", $template, $cached['path'], $cached['module']);
                    return $cached['path'];            
                }
                else
                    Debug\error("WASP.File.Resolve", "Cached path for template {} from module {} cannot be read: {}", $template, $cached['module'], $cached['path']);
            }

            $path = null;
            $found_module = null;
            foreach (self::$modules as $module => $location)
            {
                $path = $location . '/template/' . $template;
                if (file_exists($path) && is_readable($path))
                {
                    $found_module = $module;
                    break;
                }
            }

            if ($found_module !== null)
            {
                Debug\debug("WASP.File.Resolve", "Resolved template {} to path {} (module: {}) (cached)", $template, $path, $found_module);
                if (self::$cache !== null)
                {
                    self::$cache['template'][$template] = array("module" => $found_module, "path" => $path);
                    self::$cacheChanged = true;
                }
                return $path;
            }

            // Nothing was found
            return null;
        }
    }

    Resolve::initCache();
    spl_autoload_register(array('WASP\\File\\Resolve', 'autoload'));
}
