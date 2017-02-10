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

/** Cache requires Dictionary and Path, so always load it */
require_once 'Dictionary.php';
require_once 'Path.php';

/**
 * Provides automatic persistent caching facilities. You can store and retrieve
 * objects in this cache. When they are available, they'll be returned,
 * otherwise null will be returned. The cache is automatically saved to PHP
 * serialized files on shutdown, and they are loaded from these files on
 * initialization.
 */ 
class Cache
{
    public static $logger = null;
    private static $repository = array();
    private $cache_name;

    /**
     * Create a cache
     * @param $name string The name of the cache, determines the file name
     *
     */
    public function __construct($name)
    {
        $this->cache_name = $name;
        if (!isset($this->repository[$name]))
            self::loadCache($name);
    }

    /**
     * Add the hook after the configuration has been loaded, and apply invalidation to the
     * cache once it times out.
     * @param $config WASP\Dictionary The configuration to load settings from
     */
    public static function setHook($config)
    {
        register_shutdown_function(array('WASP\\Cache', 'saveCache'));

        $timeout = $config->dget('cache', 'expire', 60); // Clear out cache every minute by default
        foreach (self::$repository as $name => $cache)
        {
            $st = isset($cache['_timestamp']) ? $cache['_timestamp'] : 0;

            if (time() - $st > $timeout || $timeout === 0)
            {
                self::$repository[$name] = new Dictionary();
                self::$repository[$name]['_timestamp'] = time();
            }
        }
    }

    /**
     * Load the cache from the cache files. The data will be stored in the class-internal cache storage
     *
     * @param $name string The name of the cache to load
     */
    private static function loadCache($name)
    {
        $cache_file = Path::$CACHE . '/' . $name  . '.cache';

        if (file_exists($cache_file))
        {
            if (!is_readable($cache_file))
            {
                self::$logger->error("Cannot read cache from {}", $cache_file);
                return;
            }

            try
            {
                self::$repository[$name] = Dictionary::loadFile($cache_file, "phps");
                self::$repository[$name]['_changed'] = false;
            }
            catch (\Throwable $t)
            {
                self::$logger->error("Failure loading cache {} - removing", $name);
                self::$logger->error("Error", $t);
                if (is_writable($cache_file))
                    unlink($cache_file);
                return;
            }
        }
        else
        {
            self::$logger->info("Cache {} does not exist - creating", $cache_file);
            self::$repository[$name] = new Dictionary();
        }
    }

    /**
     * Save the cache once the script terminates. Is attached as a shutdown
     * hook by calling Cache::setHook
     */
    public static function saveCache()
    {
        $cache_dir = Path::$CACHE;
        foreach (self::$repository as $name => $cache)
        {
            if (empty($cache['_changed']))
                continue;

            unset($cache['_changed']);
            $cache_file = $cache_dir . '/' . $name . '.cache';
            $cache->saveFile($cache_file, 'phps');
        }
    }

    /**
     * Get a value from the cache
     *
     * @param $key scalar The key under which to store. Can be repeated to go deeper
     * @return mixed The requested value, or null if it doesn't exist
     */
    public function &get()
    {
        if (func_num_args() === 0)
            return self::$repository[$this->cache_name]->getAll();

        return self::$repository[$this->cache_name]->dget(func_get_args(), null);
    }
    
    /**
     * Put a value in the cache
     *
     * @param $key scalar The key under which to store. Can be repeated to go deeper.
     * @param $val mixed The value to store. Should be PHP-serializable. If
     *                   this is null, the entry will be removed from the cache
     * @return Cache Provides fluent interface
     */
    public function put($key, $val)
    {
        self::$repository[$this->cache_name]->set(func_get_args(), null);
        self::$repository[$this->cache_name]['_changed'] = true;
        return $this;
    }

    /**
     * Replace the entire contents of the cache
     *
     * @param $replacement array The replacement for the cache
     */
    public function replace(array $replacement)
    {
        self::$repository[$this->cache_name] = new Dictionary($replacement);
        self::$repository[$this->cache_name]['_changed'] = true;
        self::$repository[$this->cache_name]['_timestamp'] = time();
    }
}

Cache::$logger = new Debug\Log("WASP.Cache");
