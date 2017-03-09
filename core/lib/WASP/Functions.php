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
  * English versions of the words 'off', 'no', 'disable', 'disabled' to false.
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

function flatten_array($arg)
{
    if (!is_array_like($arg))
        throw new \InvalidArgumentException("Not an array");

    $arg = to_array($arg);
    $tgt = array();
    foreach ($arg as $arg_l2)
    {
        if (is_array_like($arg_l2))
        {
            $arg_l2 = flatten_array($arg_l2);
            foreach ($arg_l2 as $arg_l3)
                $tgt[] = $arg_l3;
        }
        else
            $tgt[] = $arg_l2;
    }
    return $tgt;
}

function check_extension($extension, $class = null, $function = null)
{
    if ($class !== null && !class_exists($class, false))
        throw new HttpError(500, "A required class does not exist: {$class}. Check if the extension $extension is installed and enabled");

    if ($function !== null && !function_exists($function))
        throw new HttpError(500, "A required function does not exist: {$class}. Check if the extension $extension is installed and enabled");
}

function compareDateInterval(\DateInterval $l, \DateInterval $r)
{
    $now = new \DateTimeImmutable();
    $a = $now->add($l);
    $b = $now->add($r);

    if ($a < $b)
        return -1;
    if ($a > $b)
        return 1;
    return 0;
}

/**
 * Convert any object to a string representation.
 *
 * @param mixed $obj The variable to convert to a string
 * @param bool $html True to add line breaks as <br>, false to add them as \n
 * @param int $depth The recursion counter. When this increases above 1, '...'
 *                   is returned
 * @return string The value converted to a string
 */
function str($obj, $html = false, $depth = 0)
{
    if (is_null($obj))
        return "NULL";

    if (is_bool($obj))
        return $obj ? "TRUE" : "FALSE";

    if (is_scalar($obj))
        return (string)$obj;

    $str = "";
    if ($obj instanceof Throwable)
    {
        $str = \WASP\Debug\Logger::exceptionToString($obj);
    }
    else if (is_object($obj) && method_exists($obj, '__toString'))
    {
        $str = (string)$obj;
    }
    elseif (is_array($obj))
    {
        if ($depth > 1)
            return '[...]';
        $vals = [];
        foreach ($obj as $k => $v)
        {
            $repr = "";
            if (!is_int($k))
                $repr = "'$k' => ";
            
            $repr .= str($v, $html, $depth + 1);
            $vals[] = $repr;
        }
        return '[' . implode(', ', $vals) . ']';
    }
    else
    {
        ob_start();
        var_dump($obj);
        $str = ob_get_contents();
        ob_end_clean();
    }

    if ($html)
    {
        $str = nl2br($str);
        $str = str_replace('  ', '&nbsp;&nbsp;', $str);
    }

    return $str;
}
