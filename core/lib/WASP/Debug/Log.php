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

namespace WASP\Debug;

const TRACE = 0;
const DEBUG = 1;
const INFO = 2;
const WARN = 3;
const WARNING = 3;
const ERROR = 4;
const CRITICAL = 5;

class Log
{
    private $module;
    private static $level = TRACE;
    private static $filename = WASP_ROOT . '/var/log/wasp.log';
    private static $file = NULL;

    private static $LEVEL_NAMES = array(
        TRACE => 'TRACE',
        DEBUG => 'DEBUG',
        INFO => 'INFO',
        WARN => 'WARNING',
        ERROR => 'ERROR',
        CRITICAL => 'CRITICAL'
    );

    public function __construct($module)
    {
        if (is_object($module))
        {
            $classname = get_class($module);
            $module = str_replace('\\', '.', $classname);
        }
        elseif (class_exists($module, false))
            $module = str_replace($module, '.', $module);

        $this->module = $module;
    }

    public static function setLevel($lvl)
    {
        if (!is_int($lvl) || $lvl < TRACE || $lvl > CRITICAL)
            throw new DomainException("Invalid log level: $lvl");

        $this->level = $lvl;
    }

    public static function html($obj)
    {
        return self::str($obj, true);
    }

    public static function str($obj, $html = false)
    {
        if (is_null($obj))
            return "NULL";

        if (is_string($obj))
            return $obj;

        if (is_bool($obj))
            return $obj ? "TRUE" : "FALSE";

        if (is_numeric($obj))
            return (string)$obj;

        $str = "";
        if ($obj instanceof \Throwable)
        {
            $str = "\nException: " . get_class($obj) . " - [" . $obj->getCode() . "] " . $obj->getMessage()
                . "\nIn " . $obj->getFile() . "(" . $obj->getLine() . ")\n";
            $str .= $obj->getTraceAsString();
        }
        else if (is_object($obj) && method_exists($obj, '__toString'))
        {
            $str = (string)$obj;
        }
        else
        {
            ob_start();
            var_dump($obj);
            $str = ob_get_contents();
            ob_end_clean();
        }

        if ($html)
            $str = nl2br($str);

        return $str;
    }

    public static function log($level, $module, $message = null)
    {
        if ($level < self::$level)
            return;

        if (is_array($level) && $module === null)
        {
            $module = $level;
            $level = array_shift($module);
        }

        if (is_array($module) && $message === null)
        {
            $message = $module;
            $module = array_shift($message);
        }

        if (is_array($message))
        {
            $parameters = $message;
            $message = array_shift($parameters);
        }
        else
        { 
            $parameters = func_get_args();
            array_splice($parameters, 0, 3);
        }

        while (count($parameters))
        {
            $pos = strpos($message, '{}');
            if ($pos === false)
                break;
            
            $param = array_shift($parameters);
            $message = substr($message, 0, $pos) . self::str($param) . substr($message, $pos + 2);
        }

        $fmt = "[" . date('Y-m-d H:i:s') . '][' . $module . ']';
        
        if (class_exists("\\WASP\\Request", false))
        {
            if (isset(\WASP\Request::$remote_ip))
                $fmt .= '[' . \WASP\Request::$remote_ip . ']';

            if (!empty(\WASP\Request::$remote_host) && \WASP\Request::$remote_host !== \WASP\Request::$remote_ip)
                $fmt .= '[' . \WASP\Request::$remote_host . ']';
        }

        $fmt .= ' ' . self::$LEVEL_NAMES[$level] . ': ';

        $fmt .= ' ' . $message;
        self::write($fmt);
    }

    private static function write($str)
    {
        if (!self::$file)
            self::$file = fopen(self::$filename, 'a');

        if (self::$file)
            fwrite(self::$file, $str . "\n");
    }

    public function trace()
    {
        self::log(TRACE, $this->module, func_get_args());
    }

    public function debug()
    {
        self::log(DEBUG, $this->module, func_get_args());
    }

    public function info()
    {
        self::log(INFO, $this->module, func_get_args());
    }

    public function warn()
    {
        self::log(WARN, $this->module, func_get_args());
    }

    public function warning()
    {
        self::log(WARN, $this->module, func_get_args());
    }

    public function error()
    {
        self::log(ERROR, $this->module, func_get_args());
    }

    public function critical()
    {
        self::log(CRITICAL, $this->module, func_get_args());
    }
}

function trace()
{
    Log::log(TRACE, func_get_args());
}

function debug()
{
    Log::log(DEBUG, func_get_args());
}

function info()
{
    Log::log(INFO, func_get_args());
}

function warn()
{
    Log::log(WARN, func_get_args());
}

function warning()
{
    Log::log(WARN, func_get_args());
}

function error()
{
    Log::log(ERROR, func_get_args());
}

function critical()
{
    Log::log(CRITICAL, func_get_args());
}
