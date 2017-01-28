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

class Arguments implements \Iterator, \ArrayAccess, \Countable
{
    private $values = array();
    private $keys = null;
    private $iterator = null;

    public function __construct(array &$values)
    {
        $this->values = &$values;
    }

    public function has($key)
    {
        return array_key_exists($key, $this->values);
    }

    public function get($key, $default = null)
    {
        if (!array_key_exists($key, $this->values))
            return $default;
        
        return $this->values[$key];
    }

    public function getAll()
    {
        return $this->values;
    }

    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }

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

    public function count()
    {
        return count($this->values);
    }
}
