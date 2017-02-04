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

namespace WASP\DB\Table;

use WASP\DB\DBException;

class ForeignKey
{
    const DO_CASCADE = 1;
    const DO_UPDATE = 2;
    const DO_NULL = 3;

    protected $name;
    protected $table;
    protected $columns = array();
    protected $referred_table;
    protected $referred_columns = array();

    protected $on_update = null;
    protected $on_delete = null;

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function getName()
    {
        if ($this->name === null)
        {
            $this->name = $this->table->getName() . "_";
            $names = array();
            foreach ($this->columns as $col)
                $names[] = $col->getName();
            $this_name .= implode("_", $names) . "_fkey";
        }
        return $this->name;
    }

    public function setReferringColumn(Column $column)
    {
        $args = func_get_args();
        foreach ($args as $arg)
        {
            if (!($arg instanceof Column))
                throw new DBException("Invalid column");
            $this->columns[] = $arg;

            $t = $arg->getTable();

            if ($t === null)
                throw new DBException("Column does not belong to a table");

            if ($this->table !== null && $this->table !== $t)
                throw new DBException("All referring columns must be in the same table");

            $this->table = $t;
        }
        return $this;
    }

    public function setReferredColumn(Column $column)
    {
        $args = func_get_args();
        foreach ($args as $arg)
        {
            if (!($arg instanceof Column))
                throw new DBException("Invalid column");

            $t = $arg->getTable();
            if ($t === null)
                throw new DBException("Column does not belong to a table");

            if ($this->referred_table !== null && $this->referred_table !== $t)
                throw new DBException("All referred columns must be in the same table");
            
            $this->referred_table = $t;
        }
        return $this;
    }

    public function setOnUpdate($action)
    {
        if ($action !== ForeignKey::DO_UPDATE &&
            $action !== ForeignKey::DO_RESTRICT &&
            $action !== ForeignKey::DO_DELETE)
            throw new DBException("Invalid on update policy: $action");
        $this->on_update = $action;
        return $this;
    }

    public function setOnDelete($action)
    {
        if ($action !== ForeignKey::DO_UPDATE &&
            $action !== ForeignKey::DO_RESTRICT &&
            $action !== ForeignKey::DO_DELETE)
            throw new DBException("Invalid on update policy: $action");
        $this->on_delete = $action;
        return $this;
    }

    public function getTable()
    {
        return $this->columns;
    }

    public function getReferredTable()
    {
        return $this->referred_table;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getReferredColumns()
    {
        return $this->referred_columns;
    }

    public function getOnUpdate()
    {
        return $this->on_update;
    }

    public function getOnDelete()
    {
        return $this->on_delete;
    }
}
