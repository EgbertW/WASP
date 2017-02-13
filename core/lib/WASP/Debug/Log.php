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

use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

class Log extends AbstractLogger
{
    private $module;
    private static $level = LogLevel::DEBUG;
    private static $filename = WASP_ROOT . '/var/log/wasp.log';
    private static $file = NULL;

    private static $LEVEL_NAMES = array(
        LogLevel::DEBUG => 'DEBUG',
        LogLevel::INFO => 'INFO',
        LogLevel::NOTICE => 'NOTICE',
        LogLevel::WARNING => 'WARNING',
        LogLevel::ERROR => 'ERROR',
        LogLevel::CRITICAL => 'CRITICAL',
        LogLevel::ALERT => 'ALERT',
        LogLevel::EMERGENCY => 'EMERGENCY'
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

    public static function setDefaultLevel($lvl)
    {
        if (!isset(self::$LEVEL_NAMES[$lvl]))
            throw new \DomainException("Invalid log level: $lvl");

        self::$level = $lvl;
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

    public function log($level, $message, array $context = array())
    {
        self::logModule($level, $this->module, $message, $context);
        return $this;
    }

    public static function logModule($level, $module, $message, array $context = array())
    {
        if ($level < self::$level)
            return;

        foreach ($context as $key => $value)
        {
            $placeholder = '{' . $key . '}';
            $strval = null;
            while (($pos = strpos($message, $placeholder)) !== false)
            {
                $strval = $strval ?: self::str($value);
                $message = substr($message, 0, $pos) . $strval . substr($message, $pos + strlen($placeholder));
            }
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
}

function debug($module, $message, array $context = array())
{
    Log::logModule(LogLevel::DEBUG, $module, $message, $context);
}

function info($module, $message, array $context = array())
{
    Log::logModule(LogLevel::INFO, $module, $message, $context);
}

function notice($module, $message, array $context = array())
{
    Log::logModule(LogLevel::NOTICE, $module, $message, $context);
}

function warn($module, $message, array $context = array())
{
    Log::logModule(LogLevel::WARN, $module, $message, $context);
}

function warning($module, $message, array $context = array())
{
    Log::logModule(LogLevel::WARN, $module, $message, $context);
}

function error($module, $message, array $context = array())
{
    Log::logModule(LogLevel::ERROR, $module, $message, $context);
}

function critical($module, $message, array $context = array())
{
    Log::logModule(LogLevel::CRITICAL, $module, $message, $context);
}

function alert($module, $message, array $context = array())
{
    Log::logModule(LogLevel::ALERT, $module, $message, $context);
}

function emergency($module, $message, array $context = array())
{
    Log::logModule(LogLevel::EMERGENCY, $module, $message, $context);
}
