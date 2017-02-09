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

use WASP\Util\Encoding;

/**
 * This class provides JSON-related functions. It can be used to
 * generate the JSON data in batches and finally output the data.
 * Calling JSON::init() will set the preferred accept-type to application/json,
 * which should trigger the output to be JSON whenever possible.
 */
class JSON
{
    /** The cache control policy */
    private static $cache_control = "max-age=0";

    /** The data to be output */
    private static $output = array();

    /** The callback function that should wrap the JSON output */
    private static $callback = null;

    /** Whether to pretty print the output or not */
    private static $pretty_print = false;

    /** 
     * Init changes the accept type of the Request to prefer JSON,
     * so that JSON will be output whenever possible.
     */
    public static function init()
    {
        Request::$accept = array(
            'application/json' => 1.0,
            '*/*' => 0.5
        );
    }

    /**
     * Remove all colllected output data
     */
    public static function clear()
    {
        self::$output = array();
    }

    /** Print JSON headers, including cache control directives
      * @codeCoverageIgnore Not checkable from unit test
      */
    public static function printHeaders()
    {
        header('Content-Type: application/json');
        header('Cache-Control: ' . self::$cache_control);
        if (self::$cache_control != "max-age=0")
            header('Pragma: cache');
    }

    /**
     * set the value for the cache control directive. If you don't set this,
     * max-age = 0 will be used.
     *
     * @param $cc string The Cache Control policy
      * @codeCoverageIgnore Not checkable from unit test
     */
    public static function setCacheControl($cc)
    {
        self::$cache_control = $cc;
    }

    /** 
     * add adds one or more values to the JSON output of the script. It accepts
     * either arrays of arguments or sequential key->value pairs. If any
     * argument is not an array, it is assumed to be a key followed by the
     * value as next argument.
     */
    public static function add()
    {
        $args = func_get_args();
        while (count($args) > 0)
        {
            $arg = array_shift($args);
            if (is_array($arg))
            {
                foreach ($arg as $key => $val)
                    self::$output[$key] = $val;
            }
            else
            {
                $key = $arg;
                $value = array_shift($args);
                self::$output[$key] = $value;
            }
        }
    }

    /**
     * Enable or disable pretty printing. The default is off.
     * 
     * @param $pprint boolean On to use newlines and indents in the output, false to omit these
     */
    public static function setPrettyPrinting($pprint)
    {
        self::$pretty_print = $pprint == true;
        return self::$pretty_print;
    }

    /**
     * Get the value for a specific JSON output variable
     * 
     * @param $field string The name of the field to return
     * @return mixed The value of the field
     */
    public static function get($field)
    {
        if (!isset(self::$output[$field]))
            return null;

        return self::$output[$field];
    }

    /** 
     * setCallback sets the callback for the JSON response.  to be used for
     * Cross Site Requests using JSONP.
     *
     * @param string $cb The name of the callback function
     */
    public static function setCallback($cb)
    {
        self::$callback = $cb;
        return self::$callback;
    }

    /**
     * Removes a field from the JSON output.
     * 
     * @param $name string The field to remove
     */
    public static function remove($name)
    {
        unset(self::$output[$name]);
    }

    /**
     * output outputs the currently set JSON values and terminates. If
     * arguments are specified, those are first added to the JSON output, as if
     * add($arguments) was called.
     * @codeCoverageIgnore Not unit testable as it terminates by design,
     *                     helper method UTF8SafeEncode and pprint are tested
     */
    public static function output()
    {
        self::init();

        $args = func_get_args();
        if (count($args))
            self::add($args);

        self::printHeaders();

        if (self::$pretty_print)
            $output = self::pprint($values, 0); 
        else
            $output = self::UTF8SafeEncode($values);

        if (self::$callback)
            echo self::$callback . "(" . $output . ");";
        else
            echo $output;

        // Flush all buffered output
        while (ob_end_flush()) ; 
        
        // Terminate the script
        exit();
    }

    /**
     * Encode the specified object, catching UTF8 errors.
     *
     * @param $obj mixed The data to output
     * @return string The JSON encoded data
     */
    public static function UTF8SafeEncode($obj)
    {
        $output = json_encode($obj);

        if ($output === false && json_last_error() === JSON_ERROR_UTF8)
        {
            $obj_fixed = Encoding::fixUTF8($obj);
            $output = json_encode($obj_fixed);
            if ($output === false && json_last_error() === JSON_ERROR_UTF8)
            {
                $rep = svar_dump($obj); 
                throw new HttpError(500, "Invalid encoding: " + $rep); 
            }
        }

        return $output;
    }

    /**
     * PrettyPrint the specified output.
     * 
     * @param $obj array The data to output
     * @return string the JSON encoded, formatted data
     */
    public static function pprint($obj, $indent = 0)
    {
        if (is_object($obj) && method_exists($obj, "jsonSerialize"))
            $obj = $obj->jsonSerialize();
        elseif (!is_array($obj))
            throw new \RuntimeException("Invalid arguments for JSON::pprint");

        $array = true;
        foreach ($obj as $key => $sub)
            if (!is_int($key))
                $array = false;

        $v = $array ? "[\n" : "{\n";
        $indent += 4;
        $tot = count($obj);
        $cur = 0;
        foreach ($obj as $key => $sub)
        {
            // Keys need to be string, otherwise they will not be quoted
            if (is_numeric($key) && !$array)
                $key = (string)$key;

            $ending = (++$cur < $tot ? ",\n" : "\n");
            $v .= str_repeat(' ', $indent);
            if (!$array)
                $v .= self::UTF8SafeEncode($key) . ': ';
            if (is_array($sub))
                $v .= self::pprint($sub, $indent) . $ending;
            elseif (null === $sub)
                $v .= 'null' . $ending;
            elseif (is_numeric($sub))
                $v .= $sub . $ending;
            elseif (is_bool($sub))
                $v .= ($sub ? "true" : "false") . $ending;
            else
                $v .= self::UTF8SafeEncode($sub) . $ending;
        }
        $v .= str_repeat(' ', $indent - 4) . ($array ? ']' : '}');
        return $v;
    }
}
