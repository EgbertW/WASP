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

use WASP\Debug\LoggerAwareStaticTrait;

/**
 * Dictionary provides a flexible way to use arrays as objects. The getters and
 * setters support multi-level retrieval and setting and provide 'null' values or
 * default values if they are absent. It also provides type checking and type casting.
 *
 * Its interface closely mimicks that of the standard ArrayObject. The major difference
 * is the existence of the static function wrap() that creates a dictionary that is bound
 * to an existing array, so that external changes to that array are reflected within the
 * Dictionary and vice versa.
 */
class Dictionary implements \Iterator, \ArrayAccess, \Countable, \Serializable, \JsonSerializable
{
    use LoggerAwareStaticTrait;

    const EXISTS = -1;
    const TYPE_BOOL = -2;
    const TYPE_NUMERIC = -3;
    const TYPE_FLOAT = -4;
    const TYPE_INT = -5;
    const TYPE_STRING = -6;
    const TYPE_ARRAY = -7;
    const TYPE_OBJECT = -8;

    protected $values;
    protected $keys = null;
    protected $iterator = null;

    public function __construct(array $values = array())
    {
        $this->values = $values; 
    }

    public static function wrap(array &$values)
    {
        $dict = new Dictionary();
        $dict->values = &$values;
        return $dict;
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
        if (is_int($last) && $last < 0 && $last >= -8)
            $type = array_pop($args);

        $val = $this->values;
        foreach ($args as $arg)
        {
            if (!is_array_like($val) || !isset($val[$arg]))
                return false;
            $val = $val[$arg];
        }

        // Check type
        switch ($type)
        {
            case Dictionary::TYPE_NUMERIC:
                return is_numeric($val);
            case Dictionary::TYPE_INT:
                return is_int_val($val);
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
     * not exist. The default value may be specified as-is or wrapped in a
     * DefVal object. The latter is useful to combine with Dictionary::get()
     * or any of the other getters.
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
            if (end($args) instanceof DefVal && $default === null)
                $default = array_pop($args);
        }
        else
        {
            $args = func_get_args();
            if (count($args) >= 2)
                $default = array_pop($args);
        }

        if ($default instanceof DefVal)
            $default = $default->value;

        $ref = &$this->values;
        foreach ($args as $arg)
        {
            if (!isset($ref[$arg]))
                return $default;
            $ref = &$ref[$arg];
        }

        if (is_array($ref))
        {
            $temp = Dictionary::wrap($ref);
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
                if (!is_int_val($val))
                    throw new \DomainException("Key " . implode('.', $args) . " is not an integer");
                return (int)$val;
            case Dictionary::TYPE_NUMERIC:
            case Dictionary::TYPE_FLOAT:
                if (!is_numeric($val))
                    throw new \DomainException("Key " . implode('.', $args) . " is not numeric");
                return (float)$val;
            case Dictionary::TYPE_STRING:
                if (!is_string($val) && !is_numeric($val))
                    throw new \DomainException("Key " . implode('.', $args) . " is not a string");
                return (string)$val;
            case Dictionary::TYPE_ARRAY:
                if (!$val instanceof Dictionary)
                    throw new \DomainException("Key " . implode('.', $args) . " is not an array");
                return $val->getAll();
            case Dictionary::TYPE_OBJECT:
                if (!is_object($val) || $val instanceof Dictionary)
                    throw new \DomainException("Key " . implode('.', $args) . " is not an object");
                return $val;
            case Dictionary::TYPE_BOOL:
                return parse_bool($val);
            default:
        }
        
        // Return the value as-is
        return $val;
    }

    /**
     * Get the key as a bool
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return bool The value as bool
     */
    public function getBool($key, $default = null)
    {
        return $this->getType(func_get_args(), Dictionary::TYPE_BOOL);
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
     * Get the parameter as a Dictionary.
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return Dictionary The section as a Dictionary. If the key does not
     *                    exist, an empty Dictionay is returned. If the key
     *                    is not array-like, it will be wrapped in an array.
     */
    public function getSection($key)
    {
        $val = $this->dget(func_get_args());
        if ($val instanceof Dictionary)
            return $val;
        $val = cast_array($val);
        return Dictionary::wrap($val);
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
     * @return array an array with the same contents as this Dictionary
     */
    public function toArray()
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

    /**
     * Add all elements in the provided array-like object to the dictionary.
     * @param Traversable $values The values to add
     * @return WASP\Dictionary Provides fluent interface
     */
    public function addAll($values)
    {
        if (!is_array_like($values))
            throw new \DomainException("Invalid value to merge: " . Debug\Logger::str($values));
        foreach ($values as $key => $val)
            $this->set($key, $val);
        return $this;
    }

    /**
     * Remove all elements from the dictionary
     * @return WASP\Dictionary Provides fluent interface
     */
    public function clear()
    {
        $keys = array_keys($this->values);
        foreach ($keys as $key)
            unset($this->values[$key]);
        return $this;
    }

    /**
     * Remove and return the last element of the dictionary
     * @return mixed The last element
     */
    public function pop()
    {
        return array_pop($this->values);
    }

    /**
     * Add an element to the end of the dictionary
     * @param mixed $element The element to add to the end
     * @return Dictionary provides fluent interface
     */
    public function push($element)
    {
        array_push($this->values, $element);
        return $this;
    }

    /**
     * Add an element to the end of the dictionary, wrapper of Dictionary#push
     * @param mixed $element The element to add to the end
     * @return Dictionary provides fluent interface
     */
    public function append($element)
    {
        return $this->push($element);
    }

    /** 
     * Remove and return the first element of the dictionary
     * @return mixed The first element
     */
    public function shift()
    {
        return array_shift($this->values);
    }

    /**
     * Add an element to the beginning of the dictionary
     * @param mixed $element The element to add to the begin
     * @return Dictionary provides fluent interface
     */
    public function unshift($element)
    {
        array_unshift($this->values, $element);
        return $this;
    }

    /**
     * Add an element to the beginning of the dictionary. Wraps
     * Dictionary#unshift
     * @param mixed $element The element to add to the begin
     * @return Dictionary provides fluent interface
     */
    public function prepend($element)
    {
        return $this->unshift($this->values, $element);
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
        $this->iterator = 0;
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
        if ($offset === null)
            $this->values[] = $value;
        else
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

    // Serializable implementation
    public function serialize()
    {
        return serialize($this->values);
    }

    public function unserialize($data)
    {
        $this->values = unserialize($data);
    }

    // Sorting
    public function ksort()
    {
        ksort($this->values);
        return $this;
    }

    public function asort()
    {
        asort($this->values); 
    }

    public function uasort($callback)
    {
        uasort($this->values, $callback);
        return $this;
    }

    public function uksort($callback)
    {
        uksort($this->values, $callback);
        return $this;
    }

    public function natcasesort()
    {
        natcasesort($this->values);
    }

    public function natsort()
    {
        natsort($this->values);
    }

    public static function loadFile($filename, $filetype = null)
    {
        if (!is_readable($filename))
            throw new IOException("File not readable: $filename");

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
                throw new IOException("Invalid JSON in $filename: " . json_last_error_msg());
            return new Dictionary($arr);
        }

        if ($ext === "phps")
        {
            $contents = file_get_contents($filename);

            $arr = call_error_exception(function () use ($contents) {
                return unserialize($contents);
            });

            self::debug("Loaded {0} bytes serialized data from: {1}", [strlen($contents), $filename]);
            return new Dictionary($arr);
        }

        if ($ext === "yaml")
        {
            self::checkYAML();
            $contents = file_get_contents($filename);
            
            $arr = call_error_exception(function () use ($contents) {
                return yaml_parse($contents);
            });

            self::debug("Loaded {0} bytes serialized YAML-data from: {1}", [strlen($contents), $filename]);
            return new Dictionary($arr);
        }

        throw new \DomainException("Invalid data type: " . $ext);
    }

    public function saveFile($filename, $filetype = null)
    {
        $f = $filename;
        $d = dirname($f);
        if (!is_dir($d))
            throw new IOException("Cannot save to $d - directory does not exist");

        if (file_exists($f) && !is_writable($f))
            throw new IOException("Cannot save to $f - file is not writable");

        if (!file_exists($f) && !is_writable($d))
            throw new IOException("Cannot save to $f - directory is not writable");
        
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
            $bytes = self::writeData($filename, $data);
            self::debug("Saved {0} bytes serialized data to: {1}", [$bytes, $filename]);
            return true;
        }

        if ($ext === "json")
        {
            $json = JSON::pprint($this->values);
            $bytes = self::writeData($filename, $json);
            self::debug("Saved {0} bytes JSON-serialized data to: {1}", [$bytes, $filename]);
            return true;
        }

        if ($ext === "yaml")
        {
            self::checkYAML();
            $yaml = yaml_emit($this->values);
            $bytes = self::writeData($filename, $yaml);
            self::debug("Saved {0} bytes YAML-serialized data to: {1}", [$bytes, $filename]);
            return true;
        }
        throw new \DomainException("Invalid data type: " . $ext);
    }

    /**
     * @codeCoverageIgnore Logging need not be tested, and cannot be called externally
     */
    private static function debug()
    {
        if (self::$logger === null && class_exists("WASP\\Debug\\Logger", false))
            self::$logger = \WASP\Debug\Logger::getLogger("WASP.Dictionary");

        if (self::$logger === null)
            return;

        call_user_func_array(array(self::$logger, "debug"), func_get_args());
    }

    /**
     * @codeCoverageIgnore We can't influence the presence of the extension in a unit test
     */
    private static function checkYAML()
    {
        if (!function_exists('yaml_parse_file'))
            throw new \RuntimeException('YAML extension is not installed - cannot handle YAML files');
    }

    /**
     * Write the data to the file. Throw an exception if it fails.
     * 
     * @param $filename string The file to write
     * @param $data string The data to write to the file
     *
     * @return int The amount of bytes written
     * @throws WASP\IOException When it failed
     * @codeCoverageIgnore We want to check the write went ok, but as most checks were alread done,
     * problems hace to be due to race conditions in the OS which are nearly impossible to simulate.
     */
    private static function writeData(string $filename, string $data)
    {
        $ret = file_put_contents($filename, $data);
        $file = new Util\File($filename);
        $file->setPermissions();

        if ($ret === false)
            throw new IOException("Failed to write JSON data to " . $filename);

        return $ret;
    }
}

// @codeCoverageIgnoreStart
Functions::load();
Dictionary::setLogger();
// @codeCoverageIgnoreEnd
