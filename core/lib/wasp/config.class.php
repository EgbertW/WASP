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
    private static $repository = array();

    private $config;
    private $filename;

    private function __construct($scope)
    {
        $path = Path::$CONFIG . '/' . $scope . '.ini';
        Debug\debug("WASP.Config", "Loading config from {}", $path);
        if (!file_exists($path))
        {
            Debug\critical("WASP.Config", "Failed to load config file {}", $path);
            if (!class_exists("WASP\\HttpError"))
                require_once WASP_LIB . '/wasp/httperror.class.php';

            throw new HttpError(500, "Configuration file is missing");
        }
        $this->filename = Path::$CONFIG . '/' . $scope . '.ini';
        $this->config = parse_ini_file($this->filename, true, INI_SCANNER_TYPED);
    }

    public static function getConfig($scope = 'main')
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
        //Debug\error("WASP", "{}", $this->config);
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

    public function save()
    {
        if (file_exists($this->filename))
        {
            if (!is_writable($this->filename))
                throw new \RuntimeException("Can not write to {$this->filename}");
            $contents = file_get_contents($this->filename);
        }
        else
            $contents = "";

        // Attempt to write config without removing comments
        $lines = explode("\n", $contents);
        $new_contents = "";
        
        $section = null;
        $leading = true;
        $section_comments = array();

        foreach ($lines as $line)
        {
            $line = trim($line);
            if (empty($line))
                continue;

            // Match sections
            if (preg_match("/^\[(.+)\]$/", $line, $matches))
            {
                $leading = false;
                if ($section !== null)
                    $new_contents .= "\n";

                if (!isset($this->config[$matches[1]]))
                {
                    // Skip this section
                    Debug\info("Removing section [$matches[1]]");
                    $section = null;
                    continue;
                }

                $section = $matches[1];
                $section_comments[$section] = array();
                continue;
            }
        
            // Don't remove comments
            if (substr($line, 0, 1) == ";" && ($section !== null || $leading = true))
            {
                if ($leading)
                    $section_comments[0] = $line;
                else
                    $section_comments[$section] = $line;
                continue;
            }
        }

        foreach ($this->config as $section => $parameters)
        {
            $comments = isset($section_comments[$section]) ? $section_comments[$section] : array();
            $lines = array_merge($comments, $parameters);
            uasort($lines, function ($l, $r) {
                $cmp1 = ltrim($l, "; \t");
                $cmp2 = ltrim($l, "; \t");
                return strncmp($l, $r);
            });

            foreach ($lines as $name => $line)
            {
                if (is_string($line) && substr($line, 0, 1) == ";")
                    $new_contents .= $line . "\n";
                else
                    $new_contents .= self::write_ini_parameter($name, $line);
            }
        }

        // Write the config file
        file_put_contents($this->filename, $new_contents);
    }

    private static function write_ini_parameter($name, $parameter)
    {
        $str = "";
        if (is_array($parameter))
        {
            foreach ($parameter as $key => $val)
            {
                $prefix = $name . "[" . $key . "]";
                $str .= self::$write_ini_parameter($prefix, $val); 
            }
            
        }

        if (is_bool($parameter))
            $str .= "$name = " . ($parameter ? "true" : "false") . "\n";
        elseif (is_null($parameter))
            $str .= "$name = null\n";
        elseif (is_numeric($parameter))
            $str .= "$name = " . $parameter . "\n";
        else
            $str .= "$name = " . str_replace('"', '\\"', $parameter) . "\n";
    }
}
