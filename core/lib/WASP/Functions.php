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

use DomainException;
use td;
use WASP\Http\Error as HttpError;

/**
 * This is a class which use is only to allow
 * this file to be Autoloaded
 */
class Functions
{
    public static function load()
    {} // NO-OP
}

/**
 * Check if the provided value contains an integer value.
 * The value may be an int, or anything convertable to an int.
 * After conversion, the string representation of the value before
 * and after conversion are compared, and if they are equal, the
 * value is considered a proper integral value.
 * 
 * @param mixed $val The value to test
 * @return boolean True when $val is considered an integral value, false
 *                 otherwise
 */
function is_int_val($val)
{
    if (is_int($val)) return true;
    if (is_bool($val)) return false;
    if (!is_string($val)) return false;

    return (string)((int)$val) === $val;
}

/** Convert any value to a bool, somewhat more intelligently than PHP does
  * itself: this function will also take strings, and it will convert 
  * English and localized versions of the words 'off', 'no', 'disable',
  * 'disabled' to false.
  * 
  * @param mixed $val Any scalar or object at all
  * @param float $float_delta Used for float comparison
  * @return boolean True when the value can be considered true, false if not
  */
function parse_bool($val, float $float_delta = 0.0001)
{
    // For booleans, the value is already known
    if (is_bool($val))
        return $val;

    // Consider some 'empty' values as false
    if (empty($val))
        return false;

    // For numeric types, consider near-0 to be false
    if (is_float($val))
        return abs($val) > $float_delta;

    // Non-empty arrays are considered true
    if (is_array($val))
        return true;

    // If it is a numeric string, consider it false if it is close to 0
    if (is_string($val) && is_numeric($val))
        return (float)abs($val) > $float_delta;

    // Parse some textual values representing a boolean
    if (is_string($val))
    {
        $lc = strtolower(trim($val));

        $words = array("disable", "disabled", "false", "no", "negative", "off");
        if (function_exists('td'))
        { // Translate if available
            $words[] = td('disable', 'core');
            $words[] = td('disabled', 'core');
            $words[] = td('false', 'core');
            $words[] = td('no', 'core');
            $words[] = td('negative', 'core');
            $words[] = td('off', 'core');
        }

        // The empty string and some words are considered false
        // Any other non-empty string is considered to be true
        return !in_array($lc, $words);
    }

    // Try to call some methods on the object if they are available
    if (is_object($val))
    {
        $opts = array(
            'bool', 'to_bool', 'tobool', 'get_bool', 'getbool', 'boolean',
            'toboolean', 'to_boolean', 'get_boolean', 'getboolean', 'val',
            'getval', 'get_val', '__tostring'
        );
        foreach ($opts as $fn)
            if (method_exists($val, $fn))
            {
                $ret = $val->$fn();

                // One last possibility to use scalar booleans as no, false, off etc
                if (is_scalar($ret))
                    return parse_bool($ret, $float_delta);
        
                // Otherwise leave it to PHP
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
    return $arg instanceof \ArrayAccess && $arg instanceof \Traversable;
}

function to_array($arg)
{
    if (!is_array_like($arg))
        throw new DomainException("Cannot convert argument to array");

    if (is_array($arg))
        return $arg;
    $arr = array();
    foreach ($arg as $key => $value)
        $arr[$key] = $value;
    return $arr;
}

function cast_array($arg)
{
    try
    {
        return to_array($arg);
    }
    catch (DomainException $e)
    {
        return empty($arg) ? array() : array($arg);
    }
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
function call_error_exception($callable, $class = IOException::class)
{
    set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontenxt) use ($class) {
        restore_error_handler();
        throw new $class($errstr, $errno);
    });
    $retval = $callable();
    restore_error_handler();
    return $retval;
}

function check_extension($extension, $class = null, $function = null)
{
    if ($class !== null && !class_exists($class, false))
        throw new HttpError(500, "A required class does not exist: {$class}. Check if the extension $extension is installed and enabled");

    if ($function !== null && !function_exists($function))
        throw new HttpError(500, "A required function does not exist: {$class}. Check if the extension $extension is installed and enabled");
}
