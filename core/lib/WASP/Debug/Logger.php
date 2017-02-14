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

use WASP\Path;
use Psr\Log\LogLevel;
use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
    private static $module_loggers = array();
    private static $filename = '/var/log/wasp.log';
    private static $file = NULL;

    private $module;
    private $level = LogLevel::DEBUG;
    private $handlers = array();

    private static $LEVEL_NAMES = array(
        LogLevel::DEBUG => array(0, 'DEBUG'),
        LogLevel::INFO => array(1, 'INFO'),
        LogLevel::NOTICE => array(2, 'NOTICE'),
        LogLevel::WARNING => array(3, 'WARNING'),
        LogLevel::ERROR => array(4, 'ERROR'),
        LogLevel::CRITICAL => array(5, 'CRITICAL'),
        LogLevel::ALERT => array(6, 'ALERT'),
        LogLevel::EMERGENCY => array(7, 'EMERGENCY')
    );

    public static function getLogger($module = "")
    {
        if (is_object($module))
            $module = get_class($module);
        $module = trim(str_replace('\\', '.', $module), ". \\");

        if (!isset(self::$module_loggers[$module]))
            self::$module_loggers[$module] = new Logger($module);

        return self::$module_loggers[$module];
    }

    private function __construct($module)
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

    public function getModule()
    {
        return $this->module;
    }

    public function isRoot()
    {
        return empty($this->module);
    }

    public function getParentLogger()
    {
        if ($this->module === "")
            return null;

        $tree = explode(".", $this->module);
        array_pop($tree);
        $parent_module = implode(".", $tree);
        return self::getLogger($parent_module);
    }

    public function setLevel($lvl)
    {
        if (!isset(self::$LEVEL_NAMES[$lvl]))
            throw new \DomainException("Invalid log level: $lvl");

        $this->level = $lvl;
        return $this;
    }

    public function addLogHandler($handler)
    {
        if (is_object($handler))
        {
            if (!method_exists($handler, 'log'))
                throw new \RuntimeException("Loghandler objects must have a method 'log'");
        }
        elseif (!is_callable($handler))
            throw new \RuntimeException("Please provide a valid callback or object as LogHandler");

        $this->handlers[] = $handler;
        return $this;
    }

    public function removeLogHandlers()
    {
        $this->handlers = array();
        return $this;
    }

    public function log($level, $message, array $context = array())
    {
        if (is_array($message))
            throw new \RuntimeException("blargh");

        if (!isset(self::$LEVEL_NAMES[$level]))
            throw new \Psr\Log\InvalidArgumentException("Invalid log level: $level");

        if (self::$LEVEL_NAMES[$level][0] < self::$LEVEL_NAMES[$this->level][0])
            return;

        if (!isset($context['_module']))
            $context['_module'] = $this->module;

        foreach ($this->handlers as $handler)
        {
            if (is_object($handler))
            {
                $handler->log($level, $message, $context);
            }
            else
            {
                call_user_func($handler, $level, $message, $context);
            }
        }

        $parent = $this->getParentLogger();
        if ($parent)
            $parent->log($level, $message, $context);
    }

    public static function fillPlaceholders($message, $context)
    {
        $message = (string)$message;
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
        return $message;
    }

    public static function writeFile($level, $message, $context)
    {
        $message = self::fillPlaceholders($message, $context);
        $module = isset($context['_module']) ? $context['_module'] : "";
        $fmt = "[" . date('Y-m-d H:i:s') . '][' . $module . ']';
        
        if (class_exists("\\WASP\\Request", false))
        {
            if (isset(\WASP\Request::$remote_ip))
                $fmt .= '[' . \WASP\Request::$remote_ip . ']';

            if (!empty(\WASP\Request::$remote_host) && \WASP\Request::$remote_host !== \WASP\Request::$remote_ip)
                $fmt .= '[' . \WASP\Request::$remote_host . ']';
        }

        $fmt .= ' ' . self::$LEVEL_NAMES[$level][1] . ': ';

        $fmt .= ' ' . $message;
        self::write($fmt);
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

    public static function logModule($level, $module, $message, array $context = array())
    {
        $log = self::getLogger($module);
        return $log->log($level, $message, $context);
    }

    private static function write($str)
    {
        if (!self::$file)
            self::$file = fopen(Path::$ROOT . self::$filename, 'a');

        if (self::$file)
            fwrite(self::$file, $str . "\n");
    }
}

function debug($module, $message, array $context = array())
{
    Logger::logModule(LogLevel::DEBUG, $module, $message, $context);
}

function info($module, $message, array $context = array())
{
    Logger::logModule(LogLevel::INFO, $module, $message, $context);
}

function notice($module, $message, array $context = array())
{
    Logger::logModule(LogLevel::NOTICE, $module, $message, $context);
}

function warn($module, $message, array $context = array())
{
    Logger::logModule(LogLevel::WARN, $module, $message, $context);
}

function warning($module, $message, array $context = array())
{
    Logger::logModule(LogLevel::WARN, $module, $message, $context);
}

function error($module, $message, array $context = array())
{
    Logger::logModule(LogLevel::ERROR, $module, $message, $context);
}

function critical($module, $message, array $context = array())
{
    Logger::logModule(LogLevel::CRITICAL, $module, $message, $context);
}

function alert($module, $message, array $context = array())
{
    Logger::logModule(LogLevel::ALERT, $module, $message, $context);
}

function emergency($module, $message, array $context = array())
{
    Logger::logModule(LogLevel::EMERGENCY, $module, $message, $context);
}
