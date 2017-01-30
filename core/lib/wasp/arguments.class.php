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

class Arguments implements \Iterator, \ArrayAccess, \Countable, \JsonSerializable
{
    const EXISTS = 0;
    const TYPE_NUMERIC = 1;
    const TYPE_FLOAT = 1;
    const TYPE_INT = 2;
    const TYPE_STRING = 3;
    const TYPE_ARRAY = 4;
    const TYPE_OBJECT = 5;

    private $values = array();
    private $keys = null;
    private $iterator = null;

    public function __construct(array &$values)
    {
        $this->values = &$values;
    }

    public function has($key, $type = Arguments::EXISTS)
    {
        if (!array_key_exists($key, $this->values))
            return false;
    
        // Check type
        $val = $this->values[$key];
        switch ($type)
        {
            case Arguments::TYPE_NUMERIC:
                return is_string($val);
            case Arguments::TYPE_INT:
                return \is_int_val($val);
            case Arguments::TYPE_STRING:
                return is_string($val);
            case Arguments::TYPE_ARRAY:
                return is_array($val);
            case Arguments::TYPE_OBJECT:
                return is_object($val);
            default:
        }
        return true; // Default to Arguments::EXIST
    }

    public function get($key, $default = null)
    {
        if (!array_key_exists($key, $this->values))
            return $default;

        if (is_array($this->values[$key]))
            return new Arguments($this->values[$key]);
        
        return $this->values[$key];
    }

    public function getType($key, $type)
    {
        if (!array_key_exists($key, $this->values))
            throw new \OutOfRangeException("Key $key does not exist");

        $val = &$this->values[$key];
        
        switch ($type)
        {
            case Arguments::TYPE_INT:
                if (!\is_int_val($val))
                    throw new \DomainException("Key $key is not an integer");
                return (int)$val;
            case Arguments::TYPE_NUMERIC:
                if (!is_numeric($val))
                    throw new \DomainException("Key $key is not numeric");
                return (float)$val;
            case Arguments::TYPE_STRING:
                if (!is_string($val) && !is_numeric($val))
                    throw new \DomainException("Key $key is not a string");
                return (string)$val;
            case Arguments::TYPE_ARRAY:
                if ($val instanceof Arguments)
                    return $val->getAll();
                if (!is_array($val))
                    throw new \DomainException("Key $key is not an array");
                return $val;
            case Arguments::TYPE_OBJECT:
                if (!is_object($val) || $val instanceof Arguments)
                    throw new \DomainException("Key $key is not an object");
                return $val;
            default:
        }
        
        // Return the value as-is, or wrap it in a argument if it is an array
        if (is_array($val))
            return new Arguments($val);
        return $val;
    }

    public function getInt($key)
    {
        return $this->getType($key, Arguments::TYPE_INT);
    }

    public function getFloat($key)
    {
        return $this->getType($key, Arguments::TYPE_FLOAT);
    }

    public function getString($key)
    {
        return $this->getType($key, Arguments::TYPE_STRING);
    }

    public function getArray($key)
    {
        return $this->getType($key, Arguments::TYPE_ARRAY);
    }

    public function getObject($key)
    {
        return $this->getType($key, Arguments::TYPE_OBJECT);
    }

    public function getAll()
    {
        return $this->values;
    }

    public function set($key, $value)
    {
        // Unwrap Arguments objects
        if ($value instanceof Arguments)
            $this->values[$key] = $value->getAll();
        else
            $this->values[$key] = $value;
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
        var_dump("rewind");
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
        return $this->get($offset);
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
}
