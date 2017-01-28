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

class Config
{
    private $config;
    private static $repository = array();

    private function __construct($scope)
    {
        $path = Path::$CONFIG . '/' . $scope . '.ini';
        \Debug\debug("WASP.Config", "Loading config from {}", $path);
        if (!file_exists($path))
        {
            \Debug\critical("WASP.Config", "Failed to load config file {}", $path);
            if (!class_exists("WASP\\HttpError"))
                require_once WASP_LIB . '/cms/httperror.class.php';

            throw new HttpError(500, "Configuration file is missing");
        }
        $this->config = parse_ini_file(Path::$CONFIG . '/' . $scope . '.ini', true);
    }

    public static function load($scope = 'main')
    {
        if (!isset(self::$repository[$scope]))
            self::$repository[$scope] = new Config($scope);

        return self::$repository[$scope];
    }

    public function has($section, $setting)
    {
        return isset($this->config[$section][$setting]);
    }

    public function get($section, $setting, $default = null)
    {
        //\Debug\error("WASP", "{}", $this->config);
        if (!$this->has($section, $setting))
            return $default;
 
        return $this->config[$section][$setting];
    }

    public function getSection($section)
    {
        if (!isset($this->config[$section]))
            return array();

        return $this->config[$section];
    }

    public function set($section, $setting, $value)
    {
        $this->config[$section][$setting] = $value;
    }
}
