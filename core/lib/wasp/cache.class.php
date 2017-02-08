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

use WASP\Debug;

class Cache
{
    public static $logger = null;
    private static $repository = array();
    private $cache_name;

    public function __construct($name)
    {
        $this->cache_name = $name;
        if (!isset($this->repository[$name]))
            self::loadCache($name);
    }

    /**
     * Add the hook after the configuration has been loaded, and apply invalidation to the
     * cache once it times out.
     * @param $config WAS\Config The configuration to load settings from
     */
    public static function setHook($config)
    {
        register_shutdown_function(array('WASP\\Cache', 'saveCache'));

        $timeout = $config->get('cache', 'expire', 60); // Clear out cache every minute by default
        foreach (self::$repository as $name => $cache)
        {
            $st = isset($cache['_timestamp']) ? $cache['_timestamp'] : 0;

            if (time() - $st > $timeout)
            {
                Debug\info("WASP.Cache", "Cache {$name} is more than {} seconds old ({}), invalidating", $timeout, $st);
                self::$repository[$name] = array('_timestamp' => time());
            }
        }
    }

    private static function loadCache($name)
    {
        $cache_file = WASP_CACHE . '/' . $name  . '.cache';

        if (file_exists($cache_file))
        {
            if (!is_readable($cache_file))
            {
                self::$logger->error("Cannot read cache from {}", $cache_file);
                return;
            }

            $data = file_get_contents($cache_file);
            $cache = unserialize($data);
            if ($cache === false)
            {
                self::$logger->error("Cache file contains invalid data: {} - removing", $cache);
                if (is_writable($cache_file))
                    unlink($cache_file);
                return;
            }

            self::$logger->info("Loaded {} bytes resolve cache data from: {}", strlen($data), $cache_file);
            unset($cache['_changed']);
            self::$repository[$name] = $cache;
        }
        else
            self::$repository[$name] = array();
    }

    /**
     * Save the cache once the script terminates.
     */
    public static function saveCache()
    {
        $cache_dir = WASP_CACHE;
        foreach (self::$repository as $name => $cache)
        {
            if (empty($cache['_changed']))
                continue;

            unset($cache['_changed']);
            $cache_file = $cache_dir . '/' . $name . '.cache';

            if (file_exists($cache_file) && !is_writable($cache_file))
            {
                Debug\error("WASP.Cache", "Cannot write {$name} cache to {$cache_file}");
                continue;
            }

            $data = serialize($cache);
            file_put_contents($cache_file, $data);
            Debug\info("WASP.Cache", "Saved {} bytes {} cache data from: {}", strlen($data), $name, $cache_file);
        }
    }

    public function &get()
    {
        $args = func_get_args();
        if (count($args) === 0)
            return self::$repository[$this->cache_name];

        $ref = &self::$repository[$this->cache_name];
        $key = array_pop($args);
        $nul = null;

        foreach ($args as $arg)
        {
            if (!isset($ref[$arg]))
                return $nul;
            $ref = &$ref[$arg];
        }

        if (!is_array($ref))
            return $nul;

        return $ref[$key];
    }
    
    public function put()
    {
        $args = func_get_args();
        if (count($args) === 0)
            throw new \RuntimeException("Need at least two arguments for Cache#put");

        $value = array_pop($args);
        $key = array_pop($args);

        $ref = &self::$repository[$this->cache_name];
        foreach ($args as $arg)
        {
            if (!isset($ref[$arg]))
                $ref[$arg] = array();

            $ref = &$ref[$arg];
        }
        $ref[$key] = $value;

        self::$repository[$this->cache_name]['_changed'] = true;
    }

    public function replace(array $replacement)
    {
        self::$repository[$this->cache_name] = $replacement;
        self::$repository[$this->cache_name]['_changed'] = true;
        self::$repository[$this->cache_name]['_timestamp'] = time();
    }
}

Cache::$logger = new Debug\Log("WASP.Cache");
