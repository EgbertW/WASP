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

class Dictionary implements \Iterator, \ArrayAccess, \Countable, \JsonSerializable
{
    const EXISTS = -1;
    const TYPE_NUMERIC = -2;
    const TYPE_FLOAT = -3;
    const TYPE_INT = -4;
    const TYPE_STRING = -5;
    const TYPE_ARRAY = -6;
    const TYPE_OBJECT = -7;

    private static $logger = null;
    private $values = array();
    private $keys = null;
    private $iterator = null;

    public function __construct(array &$values = array())
    {
        $this->values = &$values;
    }

    /**
     * Check if a key exists
     * 
     * @param $key The key to get. May be repeated to go deeper
     * @param $type The type check. Defaults to EXISTS
     * @return boolean If the key exists
     */
    public function has($key, $type = Dictionary::EXISTS)
    {
        $args = func_get_args();

        $last = end($args);     
        $type = Dictionary::EXISTS;
        if (is_int($last) && $last < 0 && $last >= -7)
            $type = array_pop($args);

        $val = $this->values;
        foreach ($args as $arg)
        {
            if (!isset($val[$arg]))
                return false;
            $val = $val[$arg];
        }

        // Check type
        switch ($type)
        {
            case Dictionary::TYPE_NUMERIC:
                return is_numeric($val);
            case Dictionary::TYPE_INT:
                return \is_int_val($val);
            case Dictionary::TYPE_FLOAT:
                return is_float($val);
            case Dictionary::TYPE_STRING:
                return is_string($val);
            case Dictionary::TYPE_ARRAY:
                return is_array($val) || $val instanceof Dictionary;
            case Dictionary::TYPE_OBJECT:
                return is_object($val);
            default:
        }
        return true; // Default to Dictionary::EXIST
    }

    /**
     * Get a value from the dictionary, with a default value when the key does
     * not exist.
     * 
     * @param $key scalar The key to get. May be repeated to go deeper
     * @param $default mixed What to return when key doesn't exist
     * @return mixed The value from the dictionary
     */
    public function &dget($key, $default = null)
    {
        if (is_array($key) && $default === null)
        {
            $args = $key;
        }
        else
        {
            $args = func_get_args();
            if (count($args) >= 2)
                $default = array_pop($args);
        }

        $ref = &$this->values;
        foreach ($args as $arg)
        {
            if (!isset($ref[$arg]))
                return $default;
            $ref = &$ref[$arg];
        }

        if (is_array($ref))
        {
            $temp = new Dictionary($ref);
            return $temp;
        }

        return $ref;
    }

    /**
     * Get a value from the dictionary. When the value does not exist, null will be returned.
     * 
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return mixed The value from the dictionary
     */
    public function &get($key)
    {
        return $this->dget(func_get_args());
    }

    /**
     * Get a value cast to a specific type.
     * @param $key scalar The key to get. May be repeated to go deeper
     * @param $type The type (one of the TYPE_* constants in Dictionary)
     * @return mixed The type as requested
     */
    public function getType($key, $type)
    {
        if (is_array($key))
        {
            $args = $key;
        }
        else
        {
            $args = func_get_args();
            $type = array_pop($args); // Type
        }
        $val = $this->dget($args);

        if ($val === null)
            throw new \OutOfRangeException("Key " . implode('.', $args) . " does not exist");

        switch ($type)
        {
            case Dictionary::TYPE_INT:
                if (!\is_int_val($val))
                    throw new \DomainException("Key " . implode('.', $args) . " is not an integer");
                return (int)$val;
            case Dictionary::TYPE_NUMERIC:
                if (!is_numeric($val))
                    throw new \DomainException("Key " . implode('.', $args) . " is not numeric");
                return (float)$val;
            case Dictionary::TYPE_STRING:
                if (!is_string($val) && !is_numeric($val))
                    throw new \DomainException("Key " . implode('.', $args) . " is not a string");
                return (string)$val;
            case Dictionary::TYPE_ARRAY:
                if ($val instanceof Dictionary)
                    return $val->getAll();
                if (!is_array($val))
                    throw new \DomainException("Key " . implode('.', $args) . " is not an array");
                return $val;
            case Dictionary::TYPE_OBJECT:
                if (!is_object($val) || $val instanceof Dictionary)
                    throw new \DomainException("Key " . implode('.', $args) . " is not an object");
                return $val;
            default:
        }
        
        // Return the value as-is, or wrap it in a argument if it is an array
        if (is_array($val))
            return new Dictionary($val);
        return $val;
    }

    /**
     * Get the key as an int
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as an int
     */
    public function getInt($key)
    {
        return $this->getType(func_get_args(), Dictionary::TYPE_INT);
    }

    /**
     * Get the key as a float
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as a float
     */
    public function getFloat($key)
    {
        return $this->getType(func_get_args(), Dictionary::TYPE_FLOAT);
    }

    /**
     * Get the key as a string
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as a string
     */
    public function getString($key)
    {
        return $this->getType(func_get_args(), Dictionary::TYPE_STRING);
    }

    /**
     * Get the key as an array
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as an array
     */
    public function getArray($key)
    {
        return $this->getType(func_get_args(), Dictionary::TYPE_ARRAY);
    }

    /**
     * Get the key as an object
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as an object
     */
    public function getObject($key)
    {
        return $this->getType(func_get_args(), Dictionary::TYPE_OBJECT);
    }

    /**
     * Get all values from the dictionary as an associative array.
     *
     * @return array A reference to the array of all values
     */
    public function &getAll()
    {
        return $this->values;
    }

    /**
     * Set a value in the dictionary
     *
     * @param $key scalar The key to set. May be repeated to go deeper
     * @param $value mixed The value to set
     * @return Dictionary Provides fluent interface
     */
    public function set($key, $value)
    {
        if (is_array($key) && $value === null)
            $args = $key;
        else
            $args = func_get_args();

        $value = array_pop($args);
        
        $parent = null;
        $key = null;
        $ref = &$this->values;
        foreach ($args as $arg)
        {
            if (!is_array($ref))
            {
                if ($parent !== null)
                    $parent[$key] = array();
                $ref = &$parent[$key];
            }
                
            if (!isset($ref[$arg]))
                $ref[$arg] = array();

            $parent = &$ref;
            $key = $arg;
            $ref = &$ref[$arg];
        }

        // Unwrap Dictionary objects
        if ($value instanceof Dictionary)
            $ref = $value->getAll();
        else
            $ref = $value;
        return $this;
    }
    
    // Iterator implementation
    public function current()
    {
        return $this->values[$this->key()];
    }

    public function key()
    {
        return $this->keys[$this->iterator];
    }

    public function rewind()
    {
        $this->keys = array_keys($this->values);    
    }

    public function next()
    {
        ++$this->iterator;
    }

    public function valid()
    {
        return array_key_exists($this->iterator, $this->keys);
    }

    // ArrayAccess implementation
    public function offsetGet($offset)
    {
        return $this->dget($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        unset($this->values[$offset]);
        $this->iterator = null;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->values);
    }

    // Countable implementation
    public function count()
    {
        return count($this->values);
    }

    // JsonSerializable implementation
    public function jsonSerialize()
    {
        return $this->values;
    }

    public static function loadFile($filename, $filetype = null)
    {
        if (!is_readable($filename))
            throw new \RuntimeException("File not readable: $filename");

        if ($filetype === null)
        {
            $extpos = strrpos($filename, ".");
            $ext = strtolower(substr($filename, $extpos + 1));
        }
        else
            $ext = strtolower($filetype);

        if ($ext === "ini")
        {
            $arr = parse_ini_file($filename, true, INI_SCANNER_TYPED);
            return new Dictionary($arr);
        }

        if ($ext === "json")
        {
            $contents = file_get_contents($filename);
            $arr = json_decode($contents, true);
            if (!is_array($arr))
                throw new \RuntimeException("Invalid JSON in $filename: " . json_last_error_msg());
            return new Dictionary($arr);
        }

        if ($ext === "phps")
        {
            $contents = file_get_contents($filename);
            $arr = unserialize($contents);
            if (!is_array($arr))
                throw new \RuntimeException("Invalid serialized PHP data in $filename");
            self::info("Loaded {} bytes serialized data from: {}", strlen($contents), $filename);
            return new Dictionary($arr);
        }

        if ($ext === "yaml")
        {
            if (!function_exists('yaml_parse_file'))
                throw new \RuntimeException('YAML extension is not installed - cannot handle YAML files');
            
            $arr = yaml_parse_file($filename);
            if (!is_Array($arr))
                throw new \RuntimeException("Invalid YAML data in $filename");
            return new Dictionary($arr);
        }

        throw new \RuntimeException("Invalid data type: " . $ext);
    }

    public function saveFile($filename, $filetype = null)
    {
        $f = $filename;
        $d = dirname($f);
        if (!is_dir($d))
            throw new \RuntimeException("Cannot save to $d - directory does not exist");

        if (file_exists($f) && !is_writable($f))
            throw new \RuntimeException("Cannot save to $f - file is not writable");

        if (!file_exists($f) && !is_writable($d))
            throw new \RuntimeException("Cannot save to $f - directory is not writable");
        
        if ($filetype === null)
        {
            $extpos = strrpos($filename, ".");
            $ext = strtolower(substr($filename, $extpos + 1));
        }
        else
            $ext = strtolower($filetype);

        if ($ext === "ini")
            return INIWriter::write($filename, $this->values);

        if ($ext === "phps")
        {
            $data = serialize($this->values);
            $ret = file_put_contents($filename, $data);
            if ($ret === false)
                throw new \RuntimeException("Failed to write serialized data to " . $filename);

            self::info("Saved {} bytes serialized data from: {}", $ret, $filename);
            return true;
        }

        if ($ext === "json")
        {
            $json = JSON::pprint($this->values);
            $ret = file_put_contents($filename, $json);

            if ($ret === false)
                throw new \RuntimeException("Failed to write JSON data to " . $cache_file);

            self::info("Saved {} bytes JSON-serialized data from: {}", $ret, $cache_file);
            return true;
        }

        if ($ext === "yaml")
        {
            if (!function_exists('yaml_emit_file'))
                throw new \RuntimeException('YAML extension is not installed - cannot handle YAML files.');
            return yaml_emit_file($filename, $this->values);
        }
    }

    private static function info()
    {
        if (self::$logger === null && class_exists("WASP\\Debug\\Log", false))
            self::$logger = new \WASP\Debug\Log("WASP.Dictionary");

        if (self::$logger === null)
            return;

        call_user_func_array(array(self::$logger, "info"), func_get_args());
    }
}
