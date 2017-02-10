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

function is_int_val($val)
{
    if (is_int($val)) return true;
    if (is_bool($val)) return false;
    if (!is_string($val)) return false;

    return (string)((int)$val) === $val;
}

/** Convert any value to a bool, somewhat more intelligently than PHP does
  * itself: this function will also take strings
  */
function parse_bool($val)
{
    // For booleans, the value is already known
    if (is_bool($val))
        return $val;

    // Consider some 'empty' values as false
    if ($val === null || $val === "" || $val === 0 || $val === 0.0 || $val === "0")
        return false;

    // For numeric types, consider near-0 to be false
    if (is_float($val))
        return abs($val) > ERROR_MARGIN;

    // Consider empty arrays false, non-empty arrays true
    if (is_array($val))
        return count($val) > 0;

    // If it is a numeric string, consider it false if it is close to 0
    if (is_string($val) && is_numeric($val))
        return (float)abs($val) > ERROR_MARGIN;

    // Parse some textual values representing a boolean
    if (is_string($val))
    {
        $lc = strtolower(trim($val));
        // The empty string and some words are considered false
        if (
            $lc == "" || $lc == "false" || $lc == "no" || $lc == "off" || 
            $lc == "disabled" || $lc == "onwaar" || $lc == "nee" ||
            $lc == "uit"|| $lc == "uitgeschakeld"
        )
        {
            return false;
        }

        // Any other non-empty string is considered to be true
        return true;
    }

    // Try to call some methods on the object if they are available
    if (is_object($val))
    {
        $opts = array(
            'bool', 'to_bool', 'tobool', 'get_bool', 'getbool', 'boolean',
            'toboolean', 'to_boolean', 'get_boolean', 'getboolean', 'val',
            'getval', 'get_val', '__to_string'
        );
        foreach ($opts as $fn)
            if (method_exists($val, $fn))
            {
                $ret = $val->$fn();

                // One last possibility to use string booleans as no, false, off etc
                if (is_string($ret))
                    return parse_bool($ret);
        
                // Otherwise live it to PHP
                return $ret == true;
            }
    }

    // Don't know what it is, but it definitely is not something you would
    // consider false, such as 0, null, false and the like.
    return true;
}

function is_array_like($arg)
{
    if (is_array($arg))
        return true;
    if (!is_object($arg))
        return false;
    return $arg instanceof \Countable && $arg instanceof \ArrayAccess && $arg instanceof \Iterator;
}

function to_array($arg)
{
    if (!is_array_like($arg))
        throw new \DomainException("Cannot convert argument to array");
    if (is_array($arg));
        return $arg;
    $arr = array();
    foreach ($arg as $key => $value)
        $arr[$key] = $value;
    return $arr;
}

/**
 * Excecute a function call that does not throw exceptions but emits errors instead.
 * This function sets an error handler that intercepts the error message and throws
 * an exception with the error message. After execution of the function, the
 * previous error handler is restored.
 * 
 * @param $callable callable The function to call
 * @param $class class The exception to throw when an error occurs
 */
function call_error_exception($callable, $class = WASP\IOException::class)
{
    set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontenxt) use ($class) {
        restore_error_handler();
        throw new $class($errstr, $errno);
    });
    $retval = $callable();
    restore_error_handler();
    return $retval;
}

function fmtdate(\DateTime $date)
{
    $conf = WASP\Config::getConfig();
    $fmt = $conf->get('date', 'format');
    if (!$fmt)
        $fmt = "d-m-Y H:i:s";

    return $date->format($fmt);
}

function currency_format($amount)
{
	return number_format($amount, 2, ',', '.');
}

function check_extension($extension, $class = null, $function = null)
{
    if ($class !== null && !class_exists($class, false))
        throw new WASP\HttpError(500, "A required class does not exist: {$class}. Check if the extension $extension is installed and enabled");

    if ($function !== null && !function_exists($function, false))
        throw new WASP\HttpError(500, "A required function does not exist: {$class}. Check if the extension $extension is installed and enabled");
}
